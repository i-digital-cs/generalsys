<?php

namespace Tests\Feature\GeneralHouse;

use App\Models\GeneralHouse\GhCustomer;
use App\Models\GeneralHouse\GhCustomerAddress;
use App\Models\GeneralHouse\GhOrder;
use App\Models\GeneralHouse\GhOrderEvent;
use App\Models\GeneralHouse\GhOrderLine;
use App\Models\GeneralHouse\GhOrderReceivedLine;
use App\Models\GeneralHouse\GhProductOffering;
use App\Models\GeneralHouse\GhZone;
use App\Models\User;
use App\Services\GeneralHouse\Orders\GhOrderStateService;
use App\Services\GeneralHouse\Orders\GhPickupEligibilityService;
use App\Support\GeneralHouse\GhCustomerType;
use App\Support\GeneralHouse\GhOrderStatus;
use App\Support\GeneralHouse\GhReceivedDiscrepancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LogisticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (! DB::table('gh_sequences')->where('name', 'orders')->exists()) {
            DB::table('gh_sequences')->insert([
                'name' => 'orders',
                'next_value' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function test_gh_business_timezone_is_asia_dubai_not_app_timezone(): void
    {
        $eligibility = app(GhPickupEligibilityService::class);
        $this->assertSame('Asia/Dubai', $eligibility->timezone());
        $this->assertNotSame(config('app.timezone'), $eligibility->timezone());
        $this->assertSame('Africa/Cairo', $eligibility->displayTimezone('Africa/Cairo'));
    }

    public function test_scheduler_promotes_later_to_pickup_idempotently(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-31 10:00:00'));

        // Relative to frozen now so SQLite wall-clock storage cannot skew eligibility.
        $order = $this->makeOrder([
            'status' => GhOrderStatus::LATER,
            'pickup_starts_at' => Carbon::now()->addHours(12),
            'pickup_ends_at' => Carbon::now()->addHours(14),
        ]);

        $svc = app(GhOrderStateService::class);
        $this->assertSame(1, $svc->promoteEligibleLaterOrders());
        $this->assertSame(GhOrderStatus::PICKUP, $order->fresh()->status);
        $this->assertSame(1, GhOrderEvent::query()->where('order_id', $order->id)->where('event_type', 'PICKUP_PROMOTED')->count());

        Artisan::call('gh:promote-pickup-eligible');
        Artisan::call('gh:promote-pickup-eligible');
        $this->assertSame(1, GhOrderEvent::query()->where('order_id', $order->id)->where('event_type', 'PICKUP_PROMOTED')->count());

        Carbon::setTestNow();
    }

    public function test_scheduler_does_not_touch_working_or_cancelled(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-31 10:00:00'));
        $working = $this->makeOrder([
            'status' => GhOrderStatus::WORKING,
            'pickup_starts_at' => Carbon::now()->addHours(2),
            'pickup_ends_at' => Carbon::now()->addHours(3),
            'picked_up_at' => now(),
        ]);
        $cancelled = $this->makeOrder([
            'status' => GhOrderStatus::CANCELLED,
            'pickup_starts_at' => Carbon::now()->addHours(2),
            'pickup_ends_at' => Carbon::now()->addHours(3),
            'cancelled_at' => now(),
        ]);

        Artisan::call('gh:promote-pickup-eligible');
        $this->assertSame(GhOrderStatus::WORKING, $working->fresh()->status);
        $this->assertSame(GhOrderStatus::CANCELLED, $cancelled->fresh()->status);
        Carbon::setTestNow();
    }

    public function test_unauthorized_driver_cannot_confirm_pickup(): void
    {
        $pickup = User::factory()->driver()->create();
        $other = User::factory()->driver()->create();
        $order = $this->makeOrder([
            'status' => GhOrderStatus::PICKUP,
            'pickup_driver_id' => $pickup->id,
        ]);
        $line = $this->attachLine($order);

        $this->actingAs($other)->post(route('gh.orders.confirm-pickup', $order), [
            'received' => [['order_line_id' => $line->id, 'offering_id' => $line->offering_id, 'quantity' => 1]],
        ])->assertForbidden();
    }

    public function test_pickup_records_additional_items_when_order_has_no_estimate_lines(): void
    {
        $pickup = User::factory()->driver()->create();
        $order = $this->makeOrder([
            'status' => GhOrderStatus::PICKUP,
            'pickup_driver_id' => $pickup->id,
        ]);
        $offering = GhProductOffering::factory()->create(['standard_price' => '12.00']);

        $this->actingAs($pickup)->post(route('gh.orders.confirm-pickup', $order), [
            'notes' => 'Driver counted garments on site',
            'received' => [
                ['offering_id' => $offering->id, 'quantity' => 3],
            ],
        ])->assertRedirect();

        $order->refresh();
        $this->assertSame(GhOrderStatus::WORKING, $order->status);
        $this->assertNotNull($order->picked_up_at);
        $this->assertSame(0, $order->lines()->count());
        $this->assertSame(1, $order->receivedLines()->count());
        $recv = $order->receivedLines()->firstOrFail();
        $this->assertNull($recv->order_line_id);
        $this->assertSame(3, (int) $recv->quantity);
        $this->assertSame(GhReceivedDiscrepancy::ADDITIONAL, $recv->discrepancy_type);
    }

    public function test_pickup_rejects_zero_quantity_when_no_estimate_lines(): void
    {
        $pickup = User::factory()->driver()->create();
        $order = $this->makeOrder([
            'status' => GhOrderStatus::PICKUP,
            'pickup_driver_id' => $pickup->id,
        ]);
        $offering = GhProductOffering::factory()->create(['standard_price' => '12.00']);

        $this->actingAs($pickup)->post(route('gh.orders.confirm-pickup', $order), [
            'received' => [
                ['offering_id' => $offering->id, 'quantity' => 0],
            ],
        ])->assertSessionHasErrors();

        $this->assertSame(GhOrderStatus::PICKUP, $order->fresh()->status);
        $this->assertSame(0, $order->receivedLines()->count());
    }

    public function test_pickup_driver_confirms_with_quantity_difference_and_idempotent_retry(): void
    {
        $manager = User::factory()->manager()->create();
        $pickup = User::factory()->driver()->create();
        $order = $this->makeOrder([
            'status' => GhOrderStatus::PICKUP,
            'pickup_driver_id' => $pickup->id,
        ]);
        $line = $this->attachLine($order, 5);
        $extra = GhProductOffering::factory()->create(['standard_price' => '7.00']);

        $payload = [
            'notes' => 'Bag photo ok',
            'received' => [
                ['order_line_id' => $line->id, 'offering_id' => $line->offering_id, 'quantity' => 3],
                ['offering_id' => $extra->id, 'quantity' => 1, 'notes' => 'Extra scarf'],
            ],
        ];

        $this->actingAs($pickup)->post(route('gh.orders.confirm-pickup', $order), $payload)->assertRedirect();

        $order->refresh();
        $this->assertSame(GhOrderStatus::WORKING, $order->status);
        $this->assertNotNull($order->picked_up_at);
        $this->assertSame((int) $pickup->id, (int) $order->pickup_confirmed_by);
        $this->assertSame(5, (int) $line->fresh()->quantity); // requested preserved

        $recv = GhOrderReceivedLine::query()->where('order_id', $order->id)->get();
        $this->assertTrue($recv->contains(fn ($r) => $r->discrepancy_type === GhReceivedDiscrepancy::SHORT && (int) $r->quantity === 3));
        $this->assertTrue($recv->contains(fn ($r) => $r->discrepancy_type === GhReceivedDiscrepancy::ADDITIONAL));

        // Duplicate confirmation is idempotent.
        $this->actingAs($pickup)->post(route('gh.orders.confirm-pickup', $order), $payload)->assertRedirect();
        $this->assertSame(1, GhOrderEvent::query()->where('order_id', $order->id)->where('event_type', 'PICKUP_CONFIRMED')->count());

        // Manager elevation also allowed (already WORKING → idempotent).
        $this->actingAs($manager)->post(route('gh.orders.confirm-pickup', $order), $payload)->assertRedirect();
    }

    public function test_working_to_delivery_blocked_without_invoice(): void
    {
        $manager = User::factory()->manager()->create();
        $order = $this->makeOrder([
            'status' => GhOrderStatus::WORKING,
            'picked_up_at' => now(),
        ]);

        $this->expectException(ValidationException::class);
        app(GhOrderStateService::class)->markDeliveryAfterInvoice($order, $manager, [
            'invoice_id' => 999,
            'invoice_number' => 'FAKE',
        ]);
    }

    public function test_cannot_complete_from_working(): void
    {
        $manager = User::factory()->manager()->create();
        $delivery = User::factory()->driver()->create();
        $order = $this->makeOrder([
            'status' => GhOrderStatus::WORKING,
            'picked_up_at' => now(),
            'delivery_driver_id' => $delivery->id,
        ]);

        $this->actingAs($delivery)->post(route('gh.orders.confirm-delivery', $order), [
            'notes' => 'too early',
        ])->assertSessionHasErrors();

        $this->assertSame(GhOrderStatus::WORKING, $order->fresh()->status);
    }

    public function test_delivery_confirm_when_in_delivery_and_failed_attempt(): void
    {
        $delivery = User::factory()->driver()->create();
        $order = $this->makeOrder([
            'status' => GhOrderStatus::DELIVERY,
            'picked_up_at' => now(),
            'invoice_issued_at' => now(),
            'delivery_driver_id' => $delivery->id,
        ]);

        $this->actingAs($delivery)->post(route('gh.orders.failed-delivery', $order), [
            'notes' => 'No answer',
        ])->assertRedirect();
        $this->assertSame(GhOrderStatus::DELIVERY, $order->fresh()->status);
        $this->assertDatabaseHas('gh_order_attempts', [
            'order_id' => $order->id,
            'attempt_type' => 'DELIVERY',
            'outcome' => 'FAILED',
        ]);

        $this->actingAs($delivery)->post(route('gh.orders.confirm-delivery', $order), [
            'notes' => 'Left with reception',
        ])->assertRedirect();
        $this->assertSame(GhOrderStatus::COMPLETED, $order->fresh()->status);
    }

    public function test_cancel_and_reschedule_and_failed_pickup(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-31 08:00:00', config('general_house.timezone')));
        $manager = User::factory()->manager()->create();
        $pickup = User::factory()->driver()->create();

        $later = $this->makeOrder([
            'status' => GhOrderStatus::LATER,
            'pickup_driver_id' => $pickup->id,
            'pickup_starts_at' => Carbon::now()->addDays(5),
            'pickup_ends_at' => Carbon::now()->addDays(5)->addHours(2),
        ]);

        // Reschedule into a Dubai wall-clock window within 24h of frozen now.
        $this->actingAs($manager)->post(route('gh.orders.reschedule', $later), [
            'pickup_date' => '2026-10-31',
            'pickup_window_start' => '18:00',
            'pickup_window_end' => '20:00',
        ])->assertRedirect();
        $this->assertSame(GhOrderStatus::PICKUP, $later->fresh()->status);

        $this->actingAs($pickup)->post(route('gh.orders.failed-pickup', $later), [
            'notes' => 'Customer absent',
        ])->assertRedirect();
        $this->assertSame(GhOrderStatus::PICKUP, $later->fresh()->status);

        $this->actingAs($manager)->post(route('gh.orders.cancel', $later), [
            'cancel_reason' => 'Customer cancelled',
        ])->assertRedirect();
        $this->assertSame(GhOrderStatus::CANCELLED, $later->fresh()->status);

        Carbon::setTestNow();
    }

    public function test_driver_reassignment_independent(): void
    {
        $manager = User::factory()->manager()->create();
        $a = User::factory()->driver()->create();
        $b = User::factory()->driver()->create();
        $c = User::factory()->driver()->create();
        $order = $this->makeOrder([
            'status' => GhOrderStatus::PICKUP,
            'pickup_driver_id' => $a->id,
            'delivery_driver_id' => $b->id,
        ]);

        $this->actingAs($manager)->post(route('gh.orders.reassign-driver', $order), [
            'responsibility' => 'PICKUP',
            'driver_user_id' => $c->id,
        ])->assertRedirect();

        $order->refresh();
        $this->assertSame((int) $c->id, (int) $order->pickup_driver_id);
        $this->assertSame((int) $b->id, (int) $order->delivery_driver_id);
    }

    public function test_generic_transition_entry_is_closed(): void
    {
        $manager = User::factory()->manager()->create();
        $order = $this->makeOrder(['status' => GhOrderStatus::LATER]);
        $this->expectException(ValidationException::class);
        app(GhOrderStateService::class)->transition($order, GhOrderStatus::PICKUP, $manager, 'bypass');
    }

    public function test_eligibility_boundary_exact_24h(): void
    {
        $svc = app(GhPickupEligibilityService::class);
        $tz = $svc->timezone();
        $now = Carbon::parse('2026-10-31 12:00:00', $tz);
        $exact = Carbon::parse('2026-11-01 12:00:00', $tz);
        $justOutside = Carbon::parse('2026-11-01 12:00:01', $tz);
        $this->assertTrue($svc->isEligibleForPickup($exact, $now));
        $this->assertFalse($svc->isEligibleForPickup($justOutside, $now));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeOrder(array $overrides = []): GhOrder
    {
        $tz = config('general_house.timezone', 'Asia/Dubai');
        $zone = GhZone::factory()->create();
        $customer = GhCustomer::factory()->create(['type' => GhCustomerType::B2C]);
        $address = GhCustomerAddress::factory()->create([
            'customer_id' => $customer->id,
            'zone_id' => $zone->id,
        ]);
        $manager = User::factory()->manager()->create();

        return GhOrder::factory()->create(array_merge([
            'customer_id' => $customer->id,
            'address_id' => $address->id,
            'zone_id' => $zone->id,
            'timezone' => $tz,
            'pickup_starts_at' => Carbon::now($tz)->addDays(3)->setTime(10, 0),
            'pickup_ends_at' => Carbon::now($tz)->addDays(3)->setTime(12, 0),
            'created_by' => $manager->id,
        ], $overrides));
    }

    private function attachLine(GhOrder $order, int $qty = 1): GhOrderLine
    {
        $offering = GhProductOffering::factory()->create(['standard_price' => '10.00']);

        return GhOrderLine::factory()->create([
            'order_id' => $order->id,
            'offering_id' => $offering->id,
            'quantity' => $qty,
            'unit_price_estimate' => '10.00',
            'product_name_ar' => 'منتج',
            'product_name_en' => 'Product',
            'treatment_name_ar' => 'كي',
            'treatment_name_en' => 'Iron',
        ]);
    }
}
