<?php

namespace Tests\Feature\GeneralHouse;

use App\Models\GeneralHouse\GhCustomer;
use App\Models\GeneralHouse\GhCustomerAddress;
use App\Models\GeneralHouse\GhInvoice;
use App\Models\GeneralHouse\GhOrder;
use App\Models\GeneralHouse\GhOrderLine;
use App\Models\GeneralHouse\GhOrderReceivedLine;
use App\Models\GeneralHouse\GhProductOffering;
use App\Models\GeneralHouse\GhZone;
use App\Models\User;
use App\Services\GeneralHouse\Invoices\GhInvoiceIssuanceService;
use App\Services\GeneralHouse\Invoices\GhInvoiceService;
use App\Support\GeneralHouse\GhAttemptReason;
use App\Support\GeneralHouse\GhCustomerType;
use App\Support\GeneralHouse\GhMinOrderPolicy;
use App\Support\GeneralHouse\GhOrderStatus;
use App\Support\GeneralHouse\GhReceivedDiscrepancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DriverWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['orders', 'invoices', 'payments'] as $seq) {
            if (! DB::table('gh_sequences')->where('name', $seq)->exists()) {
                DB::table('gh_sequences')->insert([
                    'name' => $seq,
                    'next_value' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
        config(['general_house.min_order_surcharge_tax_class' => null]);
    }

    public function test_driver_dashboard_shows_only_assigned_tasks(): void
    {
        $pickup = User::factory()->driver()->create();
        $other = User::factory()->driver()->create();
        $mine = $this->makeOrder(['status' => GhOrderStatus::PICKUP, 'pickup_driver_id' => $pickup->id]);
        $theirs = $this->makeOrder(['status' => GhOrderStatus::PICKUP, 'pickup_driver_id' => $other->id]);

        $this->actingAs($pickup)->get(route('gh.driver.dashboard'))
            ->assertOk()
            ->assertSee($mine->reference, false)
            ->assertDontSee($theirs->reference, false);
    }

    public function test_cross_driver_task_access_denied(): void
    {
        $pickup = User::factory()->driver()->create();
        $delivery = User::factory()->driver()->create();
        $stranger = User::factory()->driver()->create();
        $order = $this->makeOrder([
            'status' => GhOrderStatus::PICKUP,
            'pickup_driver_id' => $pickup->id,
            'delivery_driver_id' => $delivery->id,
        ]);

        $this->actingAs($stranger)->get(route('gh.driver.pickup', $order))->assertForbidden();
        $this->actingAs($delivery)->get(route('gh.driver.pickup', $order))->assertForbidden();
        $this->actingAs($pickup)->get(route('gh.driver.delivery', $order))->assertForbidden();
        $this->actingAs($pickup)->get(route('gh.driver.pickup', $order))->assertOk();
    }

    public function test_pickup_confirmation_and_idempotent_retry(): void
    {
        $pickup = User::factory()->driver()->create();
        $order = $this->makeOrder([
            'status' => GhOrderStatus::PICKUP,
            'pickup_driver_id' => $pickup->id,
        ]);
        $line = $this->attachLine($order, 5);

        $payload = [
            'notes' => 'Bag OK',
            'received' => [
                ['order_line_id' => $line->id, 'offering_id' => $line->offering_id, 'quantity' => 4],
            ],
        ];

        $this->actingAs($pickup)
            ->post(route('gh.orders.confirm-pickup', $order), $payload)
            ->assertRedirect(route('gh.driver.pickup', $order));

        $order->refresh();
        $this->assertSame(GhOrderStatus::WORKING, $order->status);
        $this->assertSame(5, (int) $line->fresh()->quantity);
        $this->assertSame(4, (int) GhOrderReceivedLine::query()->where('order_id', $order->id)->value('quantity'));

        $this->actingAs($pickup)
            ->post(route('gh.orders.confirm-pickup', $order), $payload)
            ->assertRedirect();
        $this->assertSame(GhOrderStatus::WORKING, $order->fresh()->status);
    }

    public function test_delivery_confirmation_unpaid_and_not_from_working(): void
    {
        $manager = User::factory()->manager()->create();
        $pickup = User::factory()->driver()->create();
        $delivery = User::factory()->driver()->create();

        $invoice = $this->issueWorkingToDelivery($manager, $pickup, $delivery);
        $order = $invoice->order;

        $this->assertSame(GhOrderStatus::DELIVERY, $order->fresh()->status);
        $this->assertSame('UNPAID', $invoice->fresh()->payment_status);

        $this->actingAs($delivery)->get(route('gh.driver.delivery', $order))
            ->assertOk()
            ->assertSee(__('gh.driver.unpaid_delivery_warning'), false)
            ->assertDontSee(__('gh.payments.confirm_cash'), false);

        $this->actingAs($delivery)
            ->post(route('gh.orders.confirm-delivery', $order), ['notes' => 'Left at door'])
            ->assertRedirect(route('gh.driver.delivery', $order));
        $this->assertSame(GhOrderStatus::COMPLETED, $order->fresh()->status);
        $this->assertSame('UNPAID', $invoice->fresh()->payment_status);

        // Cannot confirm delivery while WORKING (no invoice path for driver).
        $working = $this->makeOrder([
            'status' => GhOrderStatus::WORKING,
            'picked_up_at' => now(),
            'pickup_driver_id' => $pickup->id,
            'delivery_driver_id' => $delivery->id,
        ]);
        $this->actingAs($delivery)
            ->post(route('gh.orders.confirm-delivery', $working), ['notes' => 'too early'])
            ->assertSessionHasErrors();
        $this->assertSame(GhOrderStatus::WORKING, $working->fresh()->status);
    }

    public function test_failed_attempts_with_reason_and_payment_invoice_denied(): void
    {
        $pickup = User::factory()->driver()->create();
        $order = $this->makeOrder([
            'status' => GhOrderStatus::PICKUP,
            'pickup_driver_id' => $pickup->id,
        ]);

        $this->actingAs($pickup)->post(route('gh.orders.failed-pickup', $order), [
            'reason_code' => GhAttemptReason::CUSTOMER_UNAVAILABLE,
            'notes' => 'No answer',
        ])->assertRedirect(route('gh.driver.pickup', $order));

        $this->assertDatabaseHas('gh_order_attempts', [
            'order_id' => $order->id,
            'attempt_type' => 'PICKUP',
            'outcome' => 'FAILED',
        ]);
        $this->assertSame(GhOrderStatus::PICKUP, $order->fresh()->status);

        $this->actingAs($pickup)->get(route('gh.invoices.index'))->assertForbidden();
        $this->actingAs($pickup)->get(route('gh.payments.index'))->assertForbidden();
    }

    public function test_manager_cannot_use_driver_workspace_routes(): void
    {
        $manager = User::factory()->manager()->create();
        $this->actingAs($manager)->get(route('gh.driver.dashboard'))->assertForbidden();
    }

    public function test_arabic_dashboard_renders(): void
    {
        $driver = User::factory()->driver()->create();
        $this->withSession(['locale' => 'ar'])
            ->actingAs($driver)
            ->get(route('gh.driver.dashboard'))
            ->assertOk()
            ->assertSee(__('gh.driver.dashboard', [], 'ar'), false);
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
            'latitude' => '25.2048000',
            'longitude' => '55.2708000',
        ]);
        $manager = User::factory()->manager()->create();

        return GhOrder::factory()->create(array_merge([
            'customer_id' => $customer->id,
            'address_id' => $address->id,
            'zone_id' => $zone->id,
            'timezone' => $tz,
            'pickup_starts_at' => Carbon::now($tz)->setTime(10, 0),
            'pickup_ends_at' => Carbon::now($tz)->setTime(12, 0),
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

    private function issueWorkingToDelivery(User $manager, User $pickup, User $delivery): GhInvoice
    {
        $order = $this->makeOrder([
            'status' => GhOrderStatus::WORKING,
            'picked_up_at' => Carbon::now('UTC'),
            'pickup_driver_id' => $pickup->id,
            'delivery_driver_id' => $delivery->id,
        ]);
        $line = $this->attachLine($order, 2);
        GhOrderReceivedLine::query()->create([
            'order_id' => $order->id,
            'order_line_id' => $line->id,
            'offering_id' => $line->offering_id,
            'quantity' => 2,
            'discrepancy_type' => GhReceivedDiscrepancy::MATCH,
            'sort_order' => 0,
            'product_name_ar' => 'منتج',
            'product_name_en' => 'Product',
            'treatment_name_ar' => 'كي',
            'treatment_name_en' => 'Iron',
            'unit_price_estimate' => '10.00',
            'currency' => 'AED',
            'price_source' => 'STANDARD',
        ]);
        // Zone min 0 via factory default may surcharge — set zone min 0
        GhZone::query()->whereKey($order->zone_id)->update([
            'min_order_amount' => '0.00',
            'min_order_policy' => GhMinOrderPolicy::SURCHARGE,
        ]);

        $draft = app(GhInvoiceService::class)->createDraftFromOrder($order->fresh(['zone', 'customer']), $manager);

        return app(GhInvoiceIssuanceService::class)->issue($draft, $manager, (int) $draft->draft_version);
    }
}
