<?php

namespace Tests\Feature\GeneralHouse;

use App\Models\GeneralHouse\GhCustomer;
use App\Models\GeneralHouse\GhCustomerAddress;
use App\Models\GeneralHouse\GhOrder;
use App\Models\GeneralHouse\GhOrderEvent;
use App\Models\GeneralHouse\GhProductOffering;
use App\Models\GeneralHouse\GhZone;
use App\Models\User;
use App\Services\GeneralHouse\Orders\GhOrderAssignmentService;
use App\Services\GeneralHouse\Orders\GhOrderBoardService;
use App\Services\GeneralHouse\Orders\GhOrderService;
use App\Services\GeneralHouse\Orders\GhOrderStateService;
use App\Services\GeneralHouse\Orders\GhOrderWarningService;
use App\Support\GeneralHouse\GhCatalogStatus;
use App\Support\GeneralHouse\GhCustomerType;
use App\Support\GeneralHouse\GhOrderBoardTab;
use App\Support\GeneralHouse\GhOrderEventType;
use App\Support\GeneralHouse\GhOrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MandatoryDeliveryScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_create_without_delivery_date_fails_validation(): void
    {
        app()->setLocale('en');
        [$manager, $driver, $customer, $address, $offering] = $this->baseFixture();

        $response = $this->actingAs($manager)->from(route('gh.orders.create'))->post(route('gh.orders.store'), [
            'customer_id' => $customer->id,
            'address_id' => $address->id,
            'lines' => [['offering_id' => $offering->id, 'quantity' => 1]],
            'scheduled_pickup_at' => '2026-11-16 10:00',
            'scheduled_delivery_time' => '14:00',
            'pickup_driver_id' => $driver->id,
        ]);

        $response->assertSessionHasErrors(['scheduled_delivery_date']);
        $this->assertSame(0, GhOrder::query()->count());
        $errors = session('errors');
        $this->assertNotNull($errors);
        $this->assertSame(
            __('gh.errors.delivery_schedule_required_on_create'),
            $errors->first('scheduled_delivery_date')
        );
    }

    public function test_create_without_delivery_time_fails_validation(): void
    {
        app()->setLocale('en');
        [$manager, $driver, $customer, $address, $offering] = $this->baseFixture();

        $response = $this->actingAs($manager)->from(route('gh.orders.create'))->post(route('gh.orders.store'), [
            'customer_id' => $customer->id,
            'address_id' => $address->id,
            'lines' => [['offering_id' => $offering->id, 'quantity' => 1]],
            'scheduled_pickup_at' => '2026-11-16 10:00',
            'scheduled_delivery_date' => '2026-11-16',
            'pickup_driver_id' => $driver->id,
        ]);

        $response->assertSessionHasErrors(['scheduled_delivery_time']);
        $this->assertSame(0, GhOrder::query()->count());
        $this->assertSame(
            'Please select a delivery date and time before creating the order.',
            session('errors')->first('scheduled_delivery_time')
        );
    }

    public function test_create_with_valid_delivery_schedule_succeeds(): void
    {
        $tz = 'Asia/Dubai';
        Carbon::setTestNow(Carbon::parse('2026-11-15 09:00:00', $tz));
        [$manager, $driver, $customer, $address, $offering] = $this->baseFixture();

        $this->actingAs($manager)->post(route('gh.orders.store'), [
            'customer_id' => $customer->id,
            'address_id' => $address->id,
            'lines' => [['offering_id' => $offering->id, 'quantity' => 1]],
            'scheduled_pickup_at' => '2026-11-16 10:00',
            'scheduled_delivery_at' => '2026-11-17 14:00',
            'pickup_driver_id' => $driver->id,
        ])->assertRedirect(route('gh.orders.index'));

        $order = GhOrder::query()->firstOrFail();
        $this->assertNotNull($order->scheduled_delivery_at);
        $this->assertSame(
            '2026-11-17 14:00',
            $order->scheduled_delivery_at->timezone($tz)->format('Y-m-d H:i')
        );
        $this->assertNull($order->delivery_driver_id);
    }

    public function test_create_without_delivery_driver_keeps_null(): void
    {
        $tz = 'Asia/Dubai';
        Carbon::setTestNow(Carbon::parse('2026-11-15 09:00:00', $tz));
        [$manager, $driver, $customer, $address, $offering] = $this->baseFixture();

        $this->actingAs($manager)->post(route('gh.orders.store'), [
            'customer_id' => $customer->id,
            'address_id' => $address->id,
            'scheduled_pickup_at' => '2026-11-16 10:00',
            'scheduled_delivery_at' => '2026-11-16 16:00',
            'pickup_driver_id' => $driver->id,
            'delivery_driver_id' => '',
        ])->assertRedirect(route('gh.orders.index'));

        $order = GhOrder::query()->firstOrFail();
        $this->assertNull($order->delivery_driver_id);
        $this->assertSame(1, $order->assignments()->where('is_current', true)->count());
    }

    public function test_explicit_unassigned_on_update_clears_delivery_driver(): void
    {
        $tz = 'Asia/Dubai';
        Carbon::setTestNow(Carbon::parse('2026-11-15 09:00:00', $tz));
        [$manager, $pickup, $customer, $address, $offering] = $this->baseFixture();
        $delivery = User::factory()->driver()->create();

        $order = app(GhOrderService::class)->create([
            'customer_id' => $customer->id,
            'address_id' => $address->id,
            'lines' => [['offering_id' => $offering->id, 'quantity' => 1]],
            'scheduled_pickup_at' => '2026-11-16 10:00',
            'scheduled_delivery_at' => '2026-11-16 16:00',
            'pickup_driver_id' => $pickup->id,
            'delivery_driver_id' => $delivery->id,
        ], $manager);

        $this->assertSame((int) $delivery->id, (int) $order->delivery_driver_id);

        $this->actingAs($manager)->put(route('gh.orders.update', $order), [
            'address_id' => $address->id,
            'lines' => [['offering_id' => $offering->id, 'quantity' => 1]],
            'scheduled_pickup_at' => '2026-11-16 10:00',
            'scheduled_delivery_at' => '2026-11-16 16:00',
            'pickup_driver_id' => $pickup->id,
            'delivery_driver_id' => '',
        ])->assertRedirect();

        $this->assertNull($order->fresh()->delivery_driver_id);
    }

    public function test_assign_delivery_driver_after_creation(): void
    {
        $tz = 'Asia/Dubai';
        Carbon::setTestNow(Carbon::parse('2026-11-15 09:00:00', $tz));
        [$manager, $pickup, $customer, $address, $offering] = $this->baseFixture();
        $delivery = User::factory()->driver()->create();

        $order = app(GhOrderService::class)->create([
            'customer_id' => $customer->id,
            'address_id' => $address->id,
            'lines' => [['offering_id' => $offering->id, 'quantity' => 1]],
            'scheduled_pickup_at' => '2026-11-16 10:00',
            'scheduled_delivery_at' => '2026-11-17 10:00',
            'pickup_driver_id' => $pickup->id,
        ], $manager);

        app(GhOrderAssignmentService::class)->assignDelivery($order, $delivery->id, $manager);
        $this->assertSame((int) $delivery->id, (int) $order->fresh()->delivery_driver_id);
    }

    public function test_future_day_promotion_at_0800_without_delivery_driver(): void
    {
        $tz = 'Asia/Dubai';
        Carbon::setTestNow(Carbon::parse('2026-11-15 10:00:00', $tz));
        [$manager, $pickup, $customer, $address, $offering] = $this->baseFixture();

        $order = app(GhOrderService::class)->create([
            'customer_id' => $customer->id,
            'address_id' => $address->id,
            'lines' => [['offering_id' => $offering->id, 'quantity' => 1]],
            'scheduled_pickup_at' => '2026-11-15 10:00',
            'scheduled_delivery_at' => '2026-11-16 14:00',
            'pickup_driver_id' => $pickup->id,
        ], $manager);
        $order->forceFill(['status' => GhOrderStatus::PICKUP])->save();

        $this->actingAs($pickup)->post(route('gh.orders.confirm-pickup', $order), [
            'notes' => 'ok',
        ])->assertRedirect();

        $this->assertSame(GhOrderStatus::WORKING, $order->fresh()->status);
        $this->assertNull($order->fresh()->delivery_driver_id);

        Carbon::setTestNow(Carbon::parse('2026-11-16 07:59:00', $tz));
        $this->assertSame(0, app(GhOrderStateService::class)->promoteEligibleWorkingToDelivery());

        Carbon::setTestNow(Carbon::parse('2026-11-16 08:00:00', $tz));
        $this->assertSame(1, app(GhOrderStateService::class)->promoteEligibleWorkingToDelivery());
        $order->refresh();
        $this->assertSame(GhOrderStatus::DELIVERY, $order->status);
        $this->assertNull($order->delivery_driver_id);
        $this->assertNull($order->invoice_issued_at);

        $codes = collect(app(GhOrderWarningService::class)->forOrder($order, GhOrderWarningService::AUDIENCE_ADMIN))
            ->pluck('code')
            ->all();
        $this->assertContains(GhOrderWarningService::CODE_NEEDS_DELIVERY_DRIVER, $codes);

        $board = app(GhOrderBoardService::class)->boardForAdmin(['tab' => GhOrderBoardTab::DELIVERY]);
        $ids = collect($board['groups'])->flatMap(fn ($g) => collect($g['orders'])->pluck('id'))->all();
        $this->assertContains((int) $order->id, $ids);

        $card = collect($board['groups'])->flatMap(fn ($g) => $g['orders'])->firstWhere('id', $order->id);
        $this->assertTrue((bool) ($card['needs_delivery_driver'] ?? false));
        $this->assertSame(GhOrderStatus::DELIVERY, $card['status']);
    }

    public function test_same_day_promotion_after_two_hours_without_driver(): void
    {
        $tz = 'Asia/Dubai';
        Carbon::setTestNow(Carbon::parse('2026-11-15 10:00:00', $tz));
        [$manager, $pickup, $customer, $address, $offering] = $this->baseFixture();

        $order = app(GhOrderService::class)->create([
            'customer_id' => $customer->id,
            'address_id' => $address->id,
            'lines' => [['offering_id' => $offering->id, 'quantity' => 1]],
            'scheduled_pickup_at' => '2026-11-15 10:00',
            'scheduled_delivery_at' => '2026-11-15 16:00',
            'pickup_driver_id' => $pickup->id,
        ], $manager);
        $order->forceFill(['status' => GhOrderStatus::PICKUP])->save();

        $this->actingAs($pickup)->post(route('gh.orders.confirm-pickup', $order), [
            'notes' => 'ok',
        ])->assertRedirect();

        Carbon::setTestNow(Carbon::parse('2026-11-15 12:00:00', $tz));
        $this->assertSame(1, app(GhOrderStateService::class)->promoteEligibleWorkingToDelivery());
        $this->assertSame(GhOrderStatus::DELIVERY, $order->fresh()->status);
        $this->assertNull($order->fresh()->delivery_driver_id);
    }

    public function test_missing_pickup_and_hold_and_resume_and_idempotent_scheduler(): void
    {
        $tz = 'Asia/Dubai';
        Carbon::setTestNow(Carbon::parse('2026-11-15 10:00:00', $tz));
        [$manager, $pickup, $customer, $address, $offering] = $this->baseFixture();

        $order = app(GhOrderService::class)->create([
            'customer_id' => $customer->id,
            'address_id' => $address->id,
            'lines' => [['offering_id' => $offering->id, 'quantity' => 1]],
            'scheduled_pickup_at' => '2026-11-15 10:00',
            'scheduled_delivery_at' => '2026-11-15 14:00',
            'pickup_driver_id' => $pickup->id,
        ], $manager);
        $order->forceFill(['status' => GhOrderStatus::PICKUP])->save();

        Carbon::setTestNow(Carbon::parse('2026-11-15 14:00:00', $tz));
        $this->assertSame(0, app(GhOrderStateService::class)->promoteEligibleWorkingToDelivery());
        $this->assertSame(GhOrderStatus::PICKUP, $order->fresh()->status);

        Carbon::setTestNow(Carbon::parse('2026-11-15 10:00:00', $tz));
        $this->actingAs($pickup)->post(route('gh.orders.confirm-pickup', $order), ['notes' => 'ok'])->assertRedirect();

        $this->actingAs($manager)->post(route('gh.orders.manual-status', $order->fresh()), [
            'to_status' => GhOrderStatus::DELIVERY,
        ])->assertRedirect();
        $this->actingAs($manager)->post(route('gh.orders.manual-status', $order->fresh()), [
            'to_status' => GhOrderStatus::WORKING,
            'reason' => 'Opened too early',
        ])->assertRedirect();

        $held = $order->fresh();
        $this->assertTrue((bool) $held->auto_promote_held);
        Carbon::setTestNow(Carbon::parse('2026-11-15 13:00:00', $tz));
        $this->assertSame(0, app(GhOrderStateService::class)->promoteEligibleWorkingToDelivery());

        $this->actingAs($manager)->post(route('gh.orders.resume-auto-promote', $held), [
            'reason' => 'resume',
        ])->assertRedirect();

        $this->assertSame(1, app(GhOrderStateService::class)->promoteEligibleWorkingToDelivery());
        $this->assertSame(0, app(GhOrderStateService::class)->promoteEligibleWorkingToDelivery());
        $this->assertSame(
            1,
            GhOrderEvent::query()->where('order_id', $order->id)->where('event_type', GhOrderEventType::DELIVERY_READY)->count()
        );
    }

    public function test_legacy_null_schedule_unchanged_until_explicitly_scheduled(): void
    {
        $tz = 'Asia/Dubai';
        Carbon::setTestNow(Carbon::parse('2026-11-15 10:00:00', $tz));
        $manager = User::factory()->manager()->create();
        $pickup = User::factory()->driver()->create();
        $zone = GhZone::factory()->create(['min_order_amount' => '0.00']);
        $customer = GhCustomer::factory()->create(['type' => GhCustomerType::B2C, 'status' => GhCatalogStatus::ACTIVE]);
        $address = GhCustomerAddress::factory()->create(['customer_id' => $customer->id, 'zone_id' => $zone->id]);
        $starts = Carbon::parse('2026-11-15 10:00', $tz)->utc();
        $order = GhOrder::factory()->create([
            'customer_id' => $customer->id,
            'address_id' => $address->id,
            'zone_id' => $zone->id,
            'status' => GhOrderStatus::WORKING,
            'pickup_starts_at' => $starts,
            'pickup_ends_at' => $starts->copy()->addHour(),
            'picked_up_at' => $starts,
            'scheduled_delivery_at' => null,
            'pickup_driver_id' => $pickup->id,
            'delivery_driver_id' => null,
            'created_by' => $manager->id,
        ]);

        $this->assertSame(0, app(GhOrderStateService::class)->promoteEligibleWorkingToDelivery());
        $this->assertSame(GhOrderStatus::WORKING, $order->fresh()->status);
        $this->assertNull($order->fresh()->scheduled_delivery_at);

        Carbon::setTestNow(Carbon::parse('2026-11-15 13:00:00', $tz));
        $this->actingAs($manager)->put(route('gh.orders.update', $order), [
            'notes' => 'set schedule',
            'scheduled_delivery_at' => '2026-11-15 14:00',
            'delivery_driver_id' => '',
        ])->assertRedirect();

        $order->refresh();
        $this->assertNotNull($order->scheduled_delivery_at);
        $this->assertSame(GhOrderStatus::DELIVERY, $order->status);
        $this->assertNull($order->delivery_driver_id);
    }

    public function test_admin_and_driver_status_labels_agree(): void
    {
        $tz = 'Asia/Dubai';
        Carbon::setTestNow(Carbon::parse('2026-11-15 12:00:00', $tz));
        [$manager, $pickup, $customer, $address, $offering] = $this->baseFixture();
        $delivery = User::factory()->driver()->create();

        $order = app(GhOrderService::class)->create([
            'customer_id' => $customer->id,
            'address_id' => $address->id,
            'lines' => [['offering_id' => $offering->id, 'quantity' => 1]],
            'scheduled_pickup_at' => '2026-11-15 10:00',
            'scheduled_delivery_at' => '2026-11-15 16:00',
            'pickup_driver_id' => $pickup->id,
            'delivery_driver_id' => $delivery->id,
        ], $manager);

        $order->forceFill([
            'status' => GhOrderStatus::DELIVERY,
            'picked_up_at' => Carbon::parse('2026-11-15 10:00', $tz)->utc(),
        ])->save();

        $admin = app(GhOrderBoardService::class)->presentCard($order->fresh(), GhOrderBoardTab::DELIVERY, 'admin', 1);
        $driver = app(GhOrderBoardService::class)->presentCard($order->fresh(), GhOrderBoardTab::DELIVERY, 'driver', 1);
        $this->assertSame($admin['status'], $driver['status']);
        $this->assertSame($admin['status_label'], $driver['status_label']);
        $this->assertSame(GhOrderStatus::DELIVERY, $admin['status']);
    }

    public function test_arabic_validation_message_for_missing_delivery(): void
    {
        app()->setLocale('ar');
        [$manager, $driver, $customer, $address, $offering] = $this->baseFixture();

        $this->actingAs($manager)->post(route('gh.orders.store'), [
            'customer_id' => $customer->id,
            'address_id' => $address->id,
            'scheduled_pickup_at' => '2026-11-16 10:00',
            'pickup_driver_id' => $driver->id,
        ])->assertSessionHasErrors();

        $errors = session('errors');
        $msg = $errors->first('scheduled_delivery_at')
            ?: $errors->first('scheduled_delivery_date')
            ?: $errors->first('scheduled_delivery_time');
        $this->assertSame('يرجى تحديد تاريخ ووقت التوصيل قبل إنشاء الطلب.', $msg);
    }

    /**
     * @return array{0: User, 1: User, 2: GhCustomer, 3: GhCustomerAddress, 4: GhProductOffering}
     */
    private function baseFixture(): array
    {
        $manager = User::factory()->manager()->create();
        $driver = User::factory()->driver()->create();
        $zone = GhZone::factory()->create(['min_order_amount' => '0.00']);
        $customer = GhCustomer::factory()->create([
            'type' => GhCustomerType::B2C,
            'status' => GhCatalogStatus::ACTIVE,
        ]);
        $address = GhCustomerAddress::factory()->create([
            'customer_id' => $customer->id,
            'zone_id' => $zone->id,
        ]);
        $offering = GhProductOffering::factory()->create([
            'status' => GhCatalogStatus::ACTIVE,
            'standard_price' => '12.00',
        ]);

        return [$manager, $driver, $customer, $address, $offering];
    }
}
