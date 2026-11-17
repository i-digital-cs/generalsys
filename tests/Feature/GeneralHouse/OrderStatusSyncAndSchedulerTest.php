<?php

namespace Tests\Feature\GeneralHouse;

use App\Models\GeneralHouse\GhCustomer;
use App\Models\GeneralHouse\GhCustomerAddress;
use App\Models\GeneralHouse\GhOrder;
use App\Models\GeneralHouse\GhOrderEvent;
use App\Models\GeneralHouse\GhZone;
use App\Models\User;
use App\Services\GeneralHouse\Orders\GhDeliveryEligibilityService;
use App\Services\GeneralHouse\Orders\GhOrderBoardService;
use App\Services\GeneralHouse\Orders\GhOrderManualTransitionService;
use App\Services\GeneralHouse\Orders\GhOrderStateService;
use App\Support\GeneralHouse\GhCustomerType;
use App\Support\GeneralHouse\GhMinOrderPolicy;
use App\Support\GeneralHouse\GhOrderBoardTab;
use App\Support\GeneralHouse\GhOrderEventType;
use App\Support\GeneralHouse\GhOrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class OrderStatusSyncAndSchedulerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_admin_and_driver_board_cards_share_persisted_status(): void
    {
        $tz = 'Asia/Dubai';
        Carbon::setTestNow(Carbon::parse('2026-11-16 10:00:00', $tz));
        $driver = User::factory()->driver()->create();
        $order = $this->makeOrder([
            'status' => GhOrderStatus::WORKING,
            'pickup_driver_id' => $driver->id,
            'delivery_driver_id' => $driver->id,
            'picked_up_at' => Carbon::parse('2026-11-16 09:00:00', $tz)->utc(),
            'scheduled_delivery_at' => null,
        ]);

        $board = app(GhOrderBoardService::class);
        $admin = $board->presentCard($order, GhOrderBoardTab::WORKING, 'admin', 1);
        $driverCard = $board->presentCard($order, GhOrderBoardTab::WORKING, 'driver', 1);

        $this->assertSame(GhOrderStatus::WORKING, $order->fresh()->status);
        $this->assertSame(GhOrderStatus::WORKING, $admin['status']);
        $this->assertSame(GhOrderStatus::WORKING, $driverCard['status']);
        $this->assertSame($admin['status_label'], $driverCard['status_label']);
        $this->assertSame('working', $admin['schedule_kind']);
        $this->assertSame('working', $driverCard['schedule_kind']);
        $this->assertTrue($admin['delivery_not_scheduled']);

        $this->actingAs(User::factory()->manager()->create())
            ->get(route('gh.orders.show', $order))
            ->assertOk()
            ->assertSee(__('gh.order_status.WORKING'), false)
            ->assertSee(__('gh.orders.delivery_not_scheduled'), false)
            ->assertSee(__('gh.orders.status_ssot_hint'), false);

        $this->actingAs($driver)
            ->get(route('gh.driver.dashboard', ['tab' => 'working']))
            ->assertOk()
            ->assertSee($order->reference, false)
            ->assertSee(__('gh.order_status.WORKING'), false);
    }

    public function test_same_day_promotion_uses_actual_pickup_day_not_scheduled_pickup_window(): void
    {
        $tz = 'Asia/Dubai';
        // Scheduled pickup was yesterday; actual pickup today; delivery today → +2h rule.
        Carbon::setTestNow(Carbon::parse('2026-11-16 12:05:00', $tz));
        $order = $this->makeOrder([
            'status' => GhOrderStatus::WORKING,
            'pickup_starts_at' => Carbon::parse('2026-11-15 10:00:00', $tz)->utc(),
            'pickup_ends_at' => Carbon::parse('2026-11-15 12:00:00', $tz)->utc(),
            'picked_up_at' => Carbon::parse('2026-11-16 10:00:00', $tz)->utc(),
            'scheduled_delivery_at' => Carbon::parse('2026-11-16 16:00:00', $tz)->utc(),
        ]);

        $eligible = app(GhDeliveryEligibilityService::class)->eligibleAt($order);
        $this->assertSame(
            Carbon::parse('2026-11-16 12:00:00', $tz)->utc()->format('Y-m-d H:i'),
            $eligible?->utc()->format('Y-m-d H:i')
        );

        $this->assertSame(1, app(GhOrderStateService::class)->promoteEligibleWorkingToDelivery());
        $this->assertSame(GhOrderStatus::DELIVERY, $order->fresh()->status);
    }

    public function test_future_day_promotes_at_0800_dubai_without_invoice(): void
    {
        $tz = 'Asia/Dubai';
        Carbon::setTestNow(Carbon::parse('2026-11-17 08:01:00', $tz));
        $order = $this->makeOrder([
            'status' => GhOrderStatus::WORKING,
            'picked_up_at' => Carbon::parse('2026-11-16 10:00:00', $tz)->utc(),
            'pickup_starts_at' => Carbon::parse('2026-11-16 10:00:00', $tz)->utc(),
            'scheduled_delivery_at' => Carbon::parse('2026-11-17 14:00:00', $tz)->utc(),
            'invoice_issued_at' => null,
        ]);

        $this->assertNull($order->invoice_issued_at);
        $this->assertSame(1, app(GhOrderStateService::class)->promoteEligibleWorkingToDelivery());
        $this->assertSame(GhOrderStatus::DELIVERY, $order->fresh()->status);
        $this->assertNull($order->fresh()->invoice_issued_at);
    }

    public function test_missing_pickup_or_schedule_blocks_auto_promotion(): void
    {
        $tz = 'Asia/Dubai';
        Carbon::setTestNow(Carbon::parse('2026-11-16 12:00:00', $tz));

        $noPickup = $this->makeOrder([
            'status' => GhOrderStatus::PICKUP,
            'picked_up_at' => null,
            'scheduled_delivery_at' => Carbon::parse('2026-11-16 14:00:00', $tz)->utc(),
        ]);
        $this->assertSame(0, app(GhOrderStateService::class)->promoteEligibleWorkingToDelivery());
        $this->assertSame(GhOrderStatus::PICKUP, $noPickup->fresh()->status);

        $noSchedule = $this->makeOrder([
            'status' => GhOrderStatus::WORKING,
            'picked_up_at' => Carbon::parse('2026-11-16 09:00:00', $tz)->utc(),
            'scheduled_delivery_at' => null,
        ]);
        $this->assertFalse(app(GhDeliveryEligibilityService::class)->isEligibleForDelivery($noSchedule));
        $this->assertSame(0, app(GhOrderStateService::class)->promoteEligibleWorkingToDelivery());
        $this->assertSame(GhOrderStatus::WORKING, $noSchedule->fresh()->status);
    }

    public function test_hold_blocks_and_resume_allows_promotion_idempotent(): void
    {
        $tz = 'Asia/Dubai';
        Carbon::setTestNow(Carbon::parse('2026-11-16 12:05:00', $tz));
        $manager = User::factory()->manager()->create();
        $order = $this->makeOrder([
            'status' => GhOrderStatus::WORKING,
            'picked_up_at' => Carbon::parse('2026-11-16 10:00:00', $tz)->utc(),
            'scheduled_delivery_at' => Carbon::parse('2026-11-16 10:00:00', $tz)->utc(),
            'auto_promote_held' => true,
            'auto_promote_held_at' => now('UTC'),
            'auto_promote_held_by' => $manager->id,
            'auto_promote_held_reason' => 'review',
        ]);

        $this->assertSame(0, app(GhOrderStateService::class)->promoteEligibleWorkingToDelivery());
        $this->assertSame(GhOrderStatus::WORKING, $order->fresh()->status);

        app(GhOrderManualTransitionService::class)->resumeAutoPromote($order, $manager, [
            'reason' => 'resume',
        ]);
        $this->assertFalse((bool) $order->fresh()->auto_promote_held);

        $this->assertSame(1, app(GhOrderStateService::class)->promoteEligibleWorkingToDelivery());
        $this->assertSame(GhOrderStatus::DELIVERY, $order->fresh()->status);
        $this->assertSame(0, app(GhOrderStateService::class)->promoteEligibleWorkingToDelivery());
        $this->assertSame(
            1,
            GhOrderEvent::query()->where('order_id', $order->id)->where('event_type', GhOrderEventType::DELIVERY_READY)->count()
        );
    }

    public function test_future_occurred_at_rejected_on_pickup_confirm(): void
    {
        $tz = 'Asia/Dubai';
        Carbon::setTestNow(Carbon::parse('2026-11-16 10:00:00', $tz));
        $driver = User::factory()->driver()->create();
        $order = $this->makeOrder([
            'status' => GhOrderStatus::PICKUP,
            'pickup_driver_id' => $driver->id,
            'picked_up_at' => null,
            'pickup_starts_at' => Carbon::parse('2026-11-16 09:00:00', $tz)->utc(),
            'pickup_ends_at' => Carbon::parse('2026-11-16 11:00:00', $tz)->utc(),
        ]);

        $this->actingAs($driver)
            ->post(route('gh.orders.confirm-pickup', $order), [
                'occurred_at' => '2026-11-22 19:54',
                'notes' => 'future stamp',
            ])
            ->assertSessionHasErrors('occurred_at');

        $this->assertSame(GhOrderStatus::PICKUP, $order->fresh()->status);
        $this->assertNull($order->fresh()->picked_up_at);
    }

    public function test_promoted_order_appears_on_driver_delivery_list(): void
    {
        $tz = 'Asia/Dubai';
        Carbon::setTestNow(Carbon::parse('2026-11-16 12:05:00', $tz));
        $driver = User::factory()->driver()->create();
        $order = $this->makeOrder([
            'status' => GhOrderStatus::WORKING,
            'pickup_driver_id' => $driver->id,
            'delivery_driver_id' => $driver->id,
            'picked_up_at' => Carbon::parse('2026-11-16 10:00:00', $tz)->utc(),
            'scheduled_delivery_at' => Carbon::parse('2026-11-16 10:00:00', $tz)->utc(),
        ]);

        app(GhOrderStateService::class)->promoteEligibleWorkingToDelivery();
        $this->assertSame(GhOrderStatus::DELIVERY, $order->fresh()->status);

        $board = app(GhOrderBoardService::class)->boardForDriver($driver, GhOrderBoardTab::DELIVERY, 1);
        $ids = collect($board['groups'])->flatMap(fn ($g) => collect($g['orders'])->pluck('id'))->all();
        $this->assertContains((int) $order->id, $ids);
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function makeOrder(array $attrs = []): GhOrder
    {
        $tz = 'Asia/Dubai';
        $zone = GhZone::factory()->create([
            'min_order_amount' => '0.00',
            'min_order_policy' => GhMinOrderPolicy::SURCHARGE,
        ]);
        $customer = GhCustomer::factory()->create(['type' => GhCustomerType::B2C]);
        $address = GhCustomerAddress::factory()->create([
            'customer_id' => $customer->id,
            'zone_id' => $zone->id,
        ]);
        $driver = User::factory()->driver()->create();

        return GhOrder::factory()->create(array_merge([
            'customer_id' => $customer->id,
            'address_id' => $address->id,
            'zone_id' => $zone->id,
            'timezone' => $tz,
            'status' => GhOrderStatus::WORKING,
            'pickup_driver_id' => $driver->id,
            'delivery_driver_id' => $driver->id,
            'pickup_starts_at' => Carbon::parse('2026-11-16 09:00:00', $tz)->utc(),
            'pickup_ends_at' => Carbon::parse('2026-11-16 11:00:00', $tz)->utc(),
            'auto_promote_held' => false,
        ], $attrs));
    }
}
