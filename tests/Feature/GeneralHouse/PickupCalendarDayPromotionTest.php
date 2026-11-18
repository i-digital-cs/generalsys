<?php

namespace Tests\Feature\GeneralHouse;

use App\Models\GeneralHouse\GhCustomer;
use App\Models\GeneralHouse\GhCustomerAddress;
use App\Models\GeneralHouse\GhInvoice;
use App\Models\GeneralHouse\GhOrder;
use App\Models\GeneralHouse\GhOrderEvent;
use App\Models\GeneralHouse\GhProductOffering;
use App\Models\GeneralHouse\GhZone;
use App\Models\User;
use App\Services\GeneralHouse\Orders\GhOrderService;
use App\Services\GeneralHouse\Orders\GhOrderStateService;
use App\Support\GeneralHouse\GhCustomerType;
use App\Support\GeneralHouse\GhOrderEventType;
use App\Support\GeneralHouse\GhOrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * LATER → PICKUP happens at 00:00 Asia/Dubai of the scheduled pickup date — never a rolling 24h window.
 */
class PickupCalendarDayPromotionTest extends TestCase
{
    use RefreshDatabase;

    private const TZ = 'Asia/Dubai';

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.timezone' => 'Africa/Cairo', 'general_house.timezone' => self::TZ]);
        if (! DB::table('gh_sequences')->where('name', 'orders')->exists()) {
            DB::table('gh_sequences')->insert([
                'name' => 'orders',
                'next_value' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_same_day_order_is_created_as_pickup(): void
    {
        $this->freezeDubai('2026-11-18 09:00:00');
        $order = $this->createOrder('2026-11-18 21:00');

        $this->assertSame(GhOrderStatus::PICKUP, $order->status);
        $this->assertNull($order->picked_up_at);
    }

    public function test_tomorrow_morning_order_stays_later_until_dubai_midnight(): void
    {
        // 23:00 today, pickup tomorrow 08:00 = only 9h ahead but a future calendar date.
        $this->freezeDubai('2026-11-18 23:00:00');
        $order = $this->createOrder('2026-11-19 08:00');
        $this->assertSame(GhOrderStatus::LATER, $order->status);

        $this->freezeDubai('2026-11-18 23:59:59');
        $this->assertSame(0, $this->states()->promoteEligibleLaterOrders());
        $this->assertSame(GhOrderStatus::LATER, $order->fresh()->status);

        $this->freezeDubai('2026-11-19 00:00:00');
        $this->assertSame(1, $this->states()->promoteEligibleLaterOrders());
        $this->assertSame(GhOrderStatus::PICKUP, $order->fresh()->status);
    }

    public function test_late_evening_creation_for_tomorrow_is_not_pickup_on_creation_day(): void
    {
        // Owner incident: created in the evening for next morning showed PICKUP immediately.
        $this->freezeDubai('2026-11-18 18:30:00');
        $order = $this->createOrder('2026-11-19 10:00');

        $this->assertSame(GhOrderStatus::LATER, $order->status);
        $this->assertSame(0, $this->states()->promoteEligibleLaterOrders());
        $this->assertSame(GhOrderStatus::LATER, $order->fresh()->status);
    }

    public function test_future_date_order_waits_for_its_own_date(): void
    {
        $this->freezeDubai('2026-11-18 10:00:00');
        $order = $this->createOrder('2026-11-22 15:00');
        $this->assertSame(GhOrderStatus::LATER, $order->status);

        foreach (['2026-11-19 12:00:00', '2026-11-20 23:00:00', '2026-11-21 23:59:59'] as $moment) {
            $this->freezeDubai($moment);
            $this->assertSame(0, $this->states()->promoteEligibleLaterOrders(), $moment);
        }
        $this->assertSame(GhOrderStatus::LATER, $order->fresh()->status);

        $this->freezeDubai('2026-11-22 00:00:01');
        $this->assertSame(1, $this->states()->promoteEligibleLaterOrders());
        $this->assertSame(GhOrderStatus::PICKUP, $order->fresh()->status);
    }

    public function test_scheduler_command_promotes_idempotently_with_audit_and_no_side_effects(): void
    {
        $this->freezeDubai('2026-11-18 20:00:00');
        $order = $this->createOrder('2026-11-19 09:00');
        $this->assertSame(GhOrderStatus::LATER, $order->status);

        $this->freezeDubai('2026-11-19 00:05:00');
        $this->artisan('gh:promote-pickup-eligible')->assertSuccessful();
        $this->artisan('gh:promote-pickup-eligible')->assertSuccessful();

        $fresh = $order->fresh();
        $this->assertSame(GhOrderStatus::PICKUP, $fresh->status);
        // Automatic promotion never implies physical pickup or invoicing.
        $this->assertNull($fresh->picked_up_at);
        $this->assertNull($fresh->pickup_confirmed_by);
        $this->assertNull($fresh->invoice_issued_at);
        $this->assertSame(0, GhInvoice::query()->where('order_id', $order->id)->count());

        $events = GhOrderEvent::query()
            ->where('order_id', $order->id)
            ->where('event_type', GhOrderEventType::PICKUP_PROMOTED)
            ->get();
        $this->assertCount(1, $events);
        $this->assertSame('automatic', $events->first()->payload['trigger'] ?? null);
        $this->assertSame('pickup_calendar_day', $events->first()->payload['rule'] ?? null);
        $this->assertSame('2026-11-19', $events->first()->payload['pickup_date'] ?? null);
    }

    public function test_promoted_pickup_cannot_reach_working_without_confirmation(): void
    {
        $this->freezeDubai('2026-11-18 20:00:00');
        $order = $this->createOrder('2026-11-19 09:00');
        $this->freezeDubai('2026-11-19 00:01:00');
        $this->states()->promoteEligibleLaterOrders();

        $manager = User::factory()->manager()->create();
        $this->actingAs($manager)->post(route('gh.orders.manual-status', $order), [
            'to_status' => GhOrderStatus::WORKING,
            'reason' => 'skip pickup',
        ])->assertSessionHasErrors('to_status');

        $this->assertSame(GhOrderStatus::PICKUP, $order->fresh()->status);
        $this->assertNull($order->fresh()->picked_up_at);
    }

    public function test_manager_can_promote_early_with_reason_and_audit(): void
    {
        $this->freezeDubai('2026-11-18 11:00:00');
        $order = $this->createOrder('2026-11-21 10:00');
        $this->assertSame(GhOrderStatus::LATER, $order->status);
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)->post(route('gh.orders.manual-status', $order), [
            'to_status' => GhOrderStatus::PICKUP,
        ])->assertSessionHasErrors('reason');
        $this->assertSame(GhOrderStatus::LATER, $order->fresh()->status);

        $this->actingAs($manager)->post(route('gh.orders.manual-status', $order), [
            'to_status' => GhOrderStatus::PICKUP,
            'reason' => 'Customer available today',
        ])->assertRedirect(route('gh.orders.show', $order));

        $this->assertSame(GhOrderStatus::PICKUP, $order->fresh()->status);
        $event = GhOrderEvent::query()
            ->where('order_id', $order->id)
            ->where('event_type', GhOrderEventType::MANUAL_STATUS_TRANSITION)
            ->latest('id')
            ->firstOrFail();
        $this->assertSame(GhOrderStatus::LATER, $event->from_status);
        $this->assertSame(GhOrderStatus::PICKUP, $event->to_status);
        $this->assertSame((int) $manager->id, (int) $event->actor_id);
        $this->assertTrue((bool) ($event->payload['early_force'] ?? false));
        $this->assertSame('Customer available today', $event->payload['reason'] ?? null);
    }

    public function test_driver_and_employee_cannot_promote_manually(): void
    {
        $this->freezeDubai('2026-11-18 11:00:00');
        $order = $this->createOrder('2026-11-21 10:00');

        foreach ([User::factory()->driver()->create(), User::factory()->employee()->create()] as $user) {
            $this->actingAs($user)->post(route('gh.orders.manual-status', $order), [
                'to_status' => GhOrderStatus::PICKUP,
                'reason' => 'not allowed',
            ])->assertForbidden();
        }

        $this->assertSame(GhOrderStatus::LATER, $order->fresh()->status);
        $this->assertSame(0, GhOrderEvent::query()
            ->where('order_id', $order->id)
            ->where('event_type', GhOrderEventType::MANUAL_STATUS_TRANSITION)
            ->count());
    }

    public function test_reschedule_to_tomorrow_moves_pickup_back_to_later(): void
    {
        $this->freezeDubai('2026-11-18 10:00:00');
        $order = $this->createOrder('2026-11-18 16:00');
        $this->assertSame(GhOrderStatus::PICKUP, $order->status);
        $manager = User::factory()->manager()->create();

        $window = app(GhOrderService::class)->parsePickupWindow(['scheduled_pickup_at' => '2026-11-19T09:00']);
        $this->states()->reschedulePickup($order, $manager, $window);

        $this->assertSame(GhOrderStatus::LATER, $order->fresh()->status);
    }

    private function freezeDubai(string $local): void
    {
        Carbon::setTestNow(Carbon::parse($local, self::TZ));
    }

    private function states(): GhOrderStateService
    {
        return app(GhOrderStateService::class);
    }

    private function createOrder(string $pickupLocal): GhOrder
    {
        $zone = GhZone::factory()->create();
        $customer = GhCustomer::factory()->create(['type' => GhCustomerType::B2C]);
        $address = GhCustomerAddress::factory()->create([
            'customer_id' => $customer->id,
            'zone_id' => $zone->id,
        ]);
        $offering = GhProductOffering::factory()->create(['standard_price' => '25.00']);
        $manager = User::factory()->manager()->create();
        $driver = User::factory()->driver()->create();

        return app(GhOrderService::class)->create([
            'customer_id' => $customer->id,
            'address_id' => $address->id,
            'lines' => [['offering_id' => $offering->id, 'quantity' => 1]],
            'scheduled_pickup_at' => $pickupLocal,
            'scheduled_delivery_at' => '2026-12-01 16:00',
            'pickup_driver_id' => $driver->id,
        ], $manager);
    }
}
