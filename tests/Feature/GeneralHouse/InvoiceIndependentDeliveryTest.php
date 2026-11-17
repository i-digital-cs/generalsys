<?php

namespace Tests\Feature\GeneralHouse;

use App\Models\GeneralHouse\GhCustomer;
use App\Models\GeneralHouse\GhCustomerAddress;
use App\Models\GeneralHouse\GhOrder;
use App\Models\GeneralHouse\GhOrderEvent;
use App\Models\GeneralHouse\GhOrderLine;
use App\Models\GeneralHouse\GhProductOffering;
use App\Models\GeneralHouse\GhZone;
use App\Models\User;
use App\Services\GeneralHouse\Invoices\GhInvoiceIssuanceService;
use App\Services\GeneralHouse\Invoices\GhInvoiceService;
use App\Services\GeneralHouse\Orders\GhDeliveryEligibilityService;
use App\Services\GeneralHouse\Orders\GhOrderBoardService;
use App\Services\GeneralHouse\Orders\GhOrderService;
use App\Services\GeneralHouse\Orders\GhOrderStateService;
use App\Support\GeneralHouse\GhCatalogStatus;
use App\Support\GeneralHouse\GhCustomerType;
use App\Support\GeneralHouse\GhOrderBoardTab;
use App\Support\GeneralHouse\GhOrderEventType;
use App\Support\GeneralHouse\GhOrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class InvoiceIndependentDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_pickup_only_order_stays_working_without_auto_delivery(): void
    {
        $tz = 'Asia/Dubai';
        Carbon::setTestNow(Carbon::parse('2026-11-15 10:00:00', $tz));
        [$manager, $pickup, $order] = $this->seedWorkingOrder(
            pickupAt: '2026-11-15 10:00',
            deliveryAt: null,
        );

        $this->assertNull($order->scheduled_delivery_at);

        $this->actingAs($pickup)->post(route('gh.orders.confirm-pickup', $order), [
            'notes' => 'picked',
        ])->assertRedirect();

        $order->refresh();
        $this->assertSame(GhOrderStatus::WORKING, $order->status);
        $this->assertNull($order->scheduled_delivery_at);
        $this->assertFalse(app(GhDeliveryEligibilityService::class)->isEligibleForDelivery($order));

        Carbon::setTestNow(Carbon::parse('2026-11-15 14:00:00', $tz));
        $this->assertSame(0, app(GhOrderStateService::class)->promoteEligibleWorkingToDelivery());
        $this->assertSame(GhOrderStatus::WORKING, $order->fresh()->status);
        $this->assertSame(
            0,
            GhOrderEvent::query()->where('order_id', $order->id)->where('event_type', GhOrderEventType::DELIVERY_READY)->count()
        );
    }

    public function test_later_delivery_scheduling_recalculates_and_promotes_once(): void
    {
        $tz = 'Asia/Dubai';
        Carbon::setTestNow(Carbon::parse('2026-11-15 10:00:00', $tz));
        [$manager, $pickup, $order] = $this->seedWorkingOrder(
            pickupAt: '2026-11-15 10:00',
            deliveryAt: null,
        );

        $this->actingAs($pickup)->post(route('gh.orders.confirm-pickup', $order), [
            'notes' => 'picked',
        ])->assertRedirect();
        $this->assertSame(GhOrderStatus::WORKING, $order->fresh()->status);

        Carbon::setTestNow(Carbon::parse('2026-11-15 12:05:00', $tz));
        $this->actingAs($manager)->put(route('gh.orders.update', $order), [
            'notes' => 'schedule delivery later',
            'scheduled_delivery_at' => '2026-11-15 14:00',
        ])->assertRedirect();

        $order->refresh();
        $this->assertNotNull($order->scheduled_delivery_at);
        $this->assertSame(GhOrderStatus::DELIVERY, $order->status);
        $this->assertSame(
            1,
            GhOrderEvent::query()->where('order_id', $order->id)->where('event_type', GhOrderEventType::DELIVERY_READY)->count()
        );
        $event = GhOrderEvent::query()
            ->where('order_id', $order->id)
            ->where('event_type', GhOrderEventType::DELIVERY_READY)
            ->first();
        $this->assertSame('schedule_reevaluation', $event?->payload['source'] ?? null);
        $this->assertSame('automatic', $event?->payload['trigger'] ?? null);

        app(GhOrderStateService::class)->promoteEligibleWorkingToDelivery();
        $this->assertSame(
            1,
            GhOrderEvent::query()->where('order_id', $order->id)->where('event_type', GhOrderEventType::DELIVERY_READY)->count()
        );
    }

    public function test_manual_delivery_ready_without_scheduled_delivery(): void
    {
        $tz = 'Asia/Dubai';
        Carbon::setTestNow(Carbon::parse('2026-11-15 10:00:00', $tz));
        [$manager, $pickup, $order] = $this->seedWorkingOrder(
            pickupAt: '2026-11-15 10:00',
            deliveryAt: null,
        );

        $this->actingAs($pickup)->post(route('gh.orders.confirm-pickup', $order), [
            'notes' => 'picked',
        ])->assertRedirect();

        $this->actingAs($manager)->post(route('gh.orders.mark-delivery-ready', $order))->assertRedirect();
        $order->refresh();
        $this->assertSame(GhOrderStatus::DELIVERY, $order->status);
        $this->assertNull($order->scheduled_delivery_at);
        $event = GhOrderEvent::query()
            ->where('order_id', $order->id)
            ->where('event_type', GhOrderEventType::DELIVERY_READY)
            ->first();
        $this->assertSame('manual', $event?->payload['trigger'] ?? null);
    }

    public function test_same_day_pickup_promotes_to_delivery_after_two_hours(): void
    {
        $tz = 'Asia/Dubai';
        Carbon::setTestNow(Carbon::parse('2026-11-15 10:00:00', $tz));
        [$manager, $pickup, $order] = $this->seedWorkingSameDayOrder();

        $this->actingAs($pickup)->post(route('gh.orders.confirm-pickup', $order), [
            'notes' => 'picked',
        ])->assertRedirect();

        $order->refresh();
        $this->assertSame(GhOrderStatus::WORKING, $order->status);
        // Delivery driver must stay unassigned unless explicitly set — never copy pickup driver.
        $this->assertNull($order->delivery_driver_id);

        Carbon::setTestNow(Carbon::parse('2026-11-15 11:59:00', $tz));
        $this->assertSame(0, app(GhOrderStateService::class)->promoteEligibleWorkingToDelivery());
        $this->assertSame(GhOrderStatus::WORKING, $order->fresh()->status);

        Carbon::setTestNow(Carbon::parse('2026-11-15 12:00:00', $tz));
        $this->assertSame(1, app(GhOrderStateService::class)->promoteEligibleWorkingToDelivery());
        $order->refresh();
        $this->assertSame(GhOrderStatus::DELIVERY, $order->status);
        $this->assertNull($order->delivery_driver_id);
        $this->assertNull($order->invoice_issued_at);

        $event = GhOrderEvent::query()
            ->where('order_id', $order->id)
            ->where('event_type', GhOrderEventType::DELIVERY_READY)
            ->first();
        $this->assertNotNull($event);
        $this->assertSame('automatic', $event->payload['trigger'] ?? null);
        $this->assertSame('scheduler', $event->payload['source'] ?? null);
    }

    public function test_future_day_promotes_at_0800_dubai_only_after_pickup(): void
    {
        $tz = 'Asia/Dubai';
        Carbon::setTestNow(Carbon::parse('2026-11-15 09:00:00', $tz));
        [$manager, $pickup, $order] = $this->seedWorkingOrder(
            pickupAt: '2026-11-15 09:00',
            deliveryAt: '2026-11-16 10:00',
        );

        Carbon::setTestNow(Carbon::parse('2026-11-16 08:00:00', $tz));
        $this->assertSame(0, app(GhOrderStateService::class)->promoteEligibleWorkingToDelivery());
        $this->assertSame(GhOrderStatus::PICKUP, $order->fresh()->status);

        Carbon::setTestNow(Carbon::parse('2026-11-15 10:00:00', $tz));
        $this->actingAs($pickup)->post(route('gh.orders.confirm-pickup', $order), [
            'notes' => 'picked',
        ])->assertRedirect();
        $this->assertSame(GhOrderStatus::WORKING, $order->fresh()->status);

        Carbon::setTestNow(Carbon::parse('2026-11-16 07:59:00', $tz));
        $this->assertSame(0, app(GhOrderStateService::class)->promoteEligibleWorkingToDelivery());

        Carbon::setTestNow(Carbon::parse('2026-11-16 08:00:00', $tz));
        $this->assertSame(1, app(GhOrderStateService::class)->promoteEligibleWorkingToDelivery());
        $this->assertSame(GhOrderStatus::DELIVERY, $order->fresh()->status);
    }

    public function test_late_pickup_on_delivery_day_waits_two_hours_from_actual_pickup(): void
    {
        $tz = 'Asia/Dubai';
        // Actual pickup calendar day drives same-day vs future-day (not the original pickup window).
        Carbon::setTestNow(Carbon::parse('2026-11-16 09:30:00', $tz));
        [$manager, $pickup, $order] = $this->seedWorkingOrder(
            pickupAt: '2026-11-15 09:00',
            deliveryAt: '2026-11-16 10:00',
            status: GhOrderStatus::PICKUP,
        );

        $this->actingAs($pickup)->post(route('gh.orders.confirm-pickup', $order), [
            'notes' => 'late pickup',
        ])->assertRedirect();

        $order->refresh();
        $this->assertSame(GhOrderStatus::WORKING, $order->status);
        $this->assertSame(
            0,
            GhOrderEvent::query()->where('order_id', $order->id)->where('event_type', GhOrderEventType::DELIVERY_READY)->count()
        );

        Carbon::setTestNow(Carbon::parse('2026-11-16 11:30:00', $tz));
        $this->assertSame(1, app(GhOrderStateService::class)->promoteEligibleWorkingToDelivery());
        $this->assertSame(GhOrderStatus::DELIVERY, $order->fresh()->status);
        $event = GhOrderEvent::query()
            ->where('order_id', $order->id)
            ->where('event_type', GhOrderEventType::DELIVERY_READY)
            ->latest('id')
            ->first();
        $this->assertSame('scheduler', $event?->payload['source'] ?? null);
    }

    public function test_pickup_after_scheduled_delivery_day_still_uses_two_hour_rule(): void
    {
        $tz = 'Asia/Dubai';
        // Pickup confirmed after the scheduled delivery date still keys off actual pickup day (+2h).
        Carbon::setTestNow(Carbon::parse('2026-11-17 09:30:00', $tz));
        [$manager, $pickup, $order] = $this->seedWorkingOrder(
            pickupAt: '2026-11-15 09:00',
            deliveryAt: '2026-11-16 10:00',
            status: GhOrderStatus::PICKUP,
        );

        $this->actingAs($pickup)->post(route('gh.orders.confirm-pickup', $order), [
            'notes' => 'very late pickup',
        ])->assertRedirect();

        $this->assertSame(GhOrderStatus::WORKING, $order->fresh()->status);

        Carbon::setTestNow(Carbon::parse('2026-11-17 11:30:00', $tz));
        $this->assertSame(1, app(GhOrderStateService::class)->promoteEligibleWorkingToDelivery());
        $this->assertSame(GhOrderStatus::DELIVERY, $order->fresh()->status);
    }

    public function test_no_invoice_driver_can_complete_delivery(): void
    {
        $tz = 'Asia/Dubai';
        Carbon::setTestNow(Carbon::parse('2026-11-15 12:05:00', $tz));
        [$manager, $pickup, $order] = $this->seedWorkingSameDayOrder();
        $order->forceFill([
            'status' => GhOrderStatus::DELIVERY,
            'picked_up_at' => Carbon::parse('2026-11-15 10:00:00', $tz)->utc(),
            'delivery_driver_id' => $pickup->id,
            'invoice_issued_at' => null,
        ])->save();

        $this->actingAs($pickup)->post(route('gh.orders.confirm-delivery', $order), [
            'notes' => 'delivered unpaid',
        ])->assertRedirect();

        $this->assertSame(GhOrderStatus::COMPLETED, $order->fresh()->status);
        $this->assertNull($order->fresh()->invoice_issued_at);
    }

    public function test_late_invoice_issuance_possible_while_in_delivery(): void
    {
        $tz = 'Asia/Dubai';
        Carbon::setTestNow(Carbon::parse('2026-11-15 10:00:00', $tz));
        [$manager, $pickup, $order] = $this->seedWorkingSameDayOrder();
        $line = $order->lines()->firstOrFail();

        $this->actingAs($pickup)->post(route('gh.orders.confirm-pickup', $order), [
            'notes' => 'picked',
            'received' => [[
                'order_line_id' => $line->id,
                'offering_id' => $line->offering_id,
                'quantity' => 1,
            ]],
        ])->assertRedirect();

        $invoice = app(GhInvoiceService::class)->createDraftFromOrder($order->fresh(), $manager);
        $this->assertGreaterThan(0, $invoice->lines()->count());

        Carbon::setTestNow(Carbon::parse('2026-11-15 12:05:00', $tz));
        app(GhOrderStateService::class)->promoteEligibleWorkingToDelivery();
        $this->assertSame(GhOrderStatus::DELIVERY, $order->fresh()->status);
        $this->assertNull($order->fresh()->invoice_issued_at);

        $issued = app(GhInvoiceIssuanceService::class)
            ->issue($invoice->fresh(), $manager, (int) $invoice->draft_version);

        $this->assertSame('ISSUED', $issued->status);
        $order->refresh();
        $this->assertSame(GhOrderStatus::DELIVERY, $order->status);
        $this->assertNotNull($order->invoice_issued_at);
        $this->assertDatabaseHas('gh_order_events', [
            'order_id' => $order->id,
            'event_type' => GhOrderEventType::INVOICE_ISSUED,
        ]);
    }

    public function test_manual_delivery_driver_not_overwritten_by_scheduler(): void
    {
        $tz = 'Asia/Dubai';
        Carbon::setTestNow(Carbon::parse('2026-11-15 10:00:00', $tz));
        $delivery = User::factory()->driver()->create();
        [$manager, $pickup, $order] = $this->seedWorkingSameDayOrder(deliveryDriver: $delivery);

        $this->actingAs($pickup)->post(route('gh.orders.confirm-pickup', $order), [
            'notes' => 'picked',
        ])->assertRedirect();
        $this->assertSame((int) $delivery->id, (int) $order->fresh()->delivery_driver_id);

        Carbon::setTestNow(Carbon::parse('2026-11-15 12:05:00', $tz));
        app(GhOrderStateService::class)->promoteEligibleWorkingToDelivery();
        app(GhOrderStateService::class)->promoteEligibleWorkingToDelivery();

        $order->refresh();
        $this->assertSame(GhOrderStatus::DELIVERY, $order->status);
        $this->assertSame((int) $delivery->id, (int) $order->delivery_driver_id);
        $this->assertSame(
            1,
            GhOrderEvent::query()->where('order_id', $order->id)->where('event_type', GhOrderEventType::DELIVERY_READY)->count()
        );
    }

    public function test_all_tab_lists_newest_first_and_search_works(): void
    {
        $tz = 'Asia/Dubai';
        Carbon::setTestNow(Carbon::parse('2026-11-15 08:00:00', $tz));
        $manager = User::factory()->manager()->create();
        $driver = User::factory()->driver()->create();
        $zone = GhZone::factory()->create();
        $customer = GhCustomer::factory()->create([
            'type' => GhCustomerType::B2C,
            'name_en' => 'Searchable Guest',
            'phone' => '0501111222',
            'status' => GhCatalogStatus::ACTIVE,
        ]);
        $address = GhCustomerAddress::factory()->create([
            'customer_id' => $customer->id,
            'zone_id' => $zone->id,
        ]);
        $offering = GhProductOffering::factory()->create([
            'status' => GhCatalogStatus::ACTIVE,
            'standard_price' => '10.00',
        ]);

        $svc = app(GhOrderService::class);
        $older = $svc->create([
            'customer_id' => $customer->id,
            'address_id' => $address->id,
            'lines' => [['offering_id' => $offering->id, 'quantity' => 1]],
            'scheduled_pickup_at' => '2026-11-16 10:00',
            'scheduled_delivery_at' => '2026-11-16 18:00',
            'pickup_driver_id' => $driver->id,
        ], $manager);

        Carbon::setTestNow(Carbon::parse('2026-11-15 09:00:00', $tz));
        $newer = $svc->create([
            'customer_id' => $customer->id,
            'address_id' => $address->id,
            'lines' => [['offering_id' => $offering->id, 'quantity' => 1]],
            'scheduled_pickup_at' => '2026-11-17 10:00',
            'scheduled_delivery_at' => '2026-11-17 18:00',
            'pickup_driver_id' => $driver->id,
        ], $manager);

        $this->assertNotNull($older->scheduled_delivery_at);
        $this->assertNotNull($newer->scheduled_delivery_at);

        $board = app(GhOrderBoardService::class)->boardForAdmin(['tab' => GhOrderBoardTab::ALL_ORDERS]);
        $this->assertSame(GhOrderBoardTab::ALL_ORDERS, $board['tab']);
        $ids = collect($board['groups'])->flatMap(fn ($g) => collect($g['orders'])->pluck('id'))->all();
        $this->assertSame([(int) $newer->id, (int) $older->id], array_slice($ids, 0, 2));

        $this->actingAs($manager)
            ->get(route('gh.orders.index', ['q' => '0501111222']))
            ->assertOk()
            ->assertSee($newer->reference, false);

        $this->actingAs($manager)
            ->get(route('gh.orders.index', ['q' => 'Searchable']))
            ->assertOk()
            ->assertSee($older->reference, false);

        $employee = User::factory()->employee()->create();
        $this->actingAs($employee)->get(route('gh.orders.index'))->assertForbidden();
    }

    public function test_utc_dubai_eligibility_math(): void
    {
        $tz = 'Asia/Dubai';
        Carbon::setTestNow(Carbon::parse('2026-11-15 10:00:00', $tz));
        [, , $order] = $this->seedWorkingSameDayOrder();
        $order->forceFill([
            'status' => GhOrderStatus::WORKING,
            'picked_up_at' => Carbon::parse('2026-11-15 10:00:00', $tz)->utc(),
        ])->save();

        $eligible = app(GhDeliveryEligibilityService::class)->eligibleAt($order->fresh());
        $this->assertSame(
            Carbon::parse('2026-11-15 12:00:00', $tz)->utc()->format('Y-m-d H:i'),
            $eligible?->utc()->format('Y-m-d H:i')
        );

        $order->forceFill(['scheduled_delivery_at' => null])->save();
        $this->assertNull(app(GhDeliveryEligibilityService::class)->eligibleAt($order->fresh()));
    }

    public function test_date_without_time_rejected(): void
    {
        $tz = 'Asia/Dubai';
        Carbon::setTestNow(Carbon::parse('2026-11-15 08:00:00', $tz));
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
            'standard_price' => '10.00',
        ]);

        app()->setLocale('en');
        $this->actingAs($manager)->post(route('gh.orders.store'), [
            'customer_id' => $customer->id,
            'address_id' => $address->id,
            'lines' => [['offering_id' => $offering->id, 'quantity' => 1]],
            'scheduled_pickup_at' => '2026-11-16 10:00',
            'scheduled_delivery_date' => '2026-11-16',
            'pickup_driver_id' => $driver->id,
        ])->assertSessionHasErrors('scheduled_delivery_time');
    }

    /**
     * @return array{0: User, 1: User, 2: GhOrder}
     */
    private function seedWorkingSameDayOrder(?User $deliveryDriver = null): array
    {
        return $this->seedWorkingOrder(
            pickupAt: '2026-11-15 10:00',
            deliveryAt: '2026-11-15 14:00',
            deliveryDriver: $deliveryDriver,
        );
    }

    /**
     * @return array{0: User, 1: User, 2: GhOrder}
     */
    private function seedWorkingOrder(
        string $pickupAt,
        ?string $deliveryAt,
        ?User $deliveryDriver = null,
        string $status = GhOrderStatus::PICKUP,
    ): array {
        $manager = User::factory()->manager()->create();
        $pickup = User::factory()->driver()->create();
        $zone = GhZone::factory()->create([
            'min_order_amount' => '0.00',
        ]);
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
            'standard_price' => '50.00',
        ]);

        // Legacy NULL schedule fixtures use the factory (create path now requires delivery).
        if ($deliveryAt === null) {
            $tz = 'Asia/Dubai';
            $starts = Carbon::parse($pickupAt, $tz)->utc();
            $order = GhOrder::factory()->create([
                'customer_id' => $customer->id,
                'address_id' => $address->id,
                'zone_id' => $zone->id,
                'status' => $status,
                'pickup_starts_at' => $starts,
                'pickup_ends_at' => $starts->copy()->addHour(),
                'scheduled_delivery_at' => null,
                'pickup_driver_id' => $pickup->id,
                'delivery_driver_id' => $deliveryDriver?->id,
                'created_by' => $manager->id,
                'updated_by' => $manager->id,
            ]);
            GhOrderLine::query()->create([
                'order_id' => $order->id,
                'offering_id' => $offering->id,
                'quantity' => 1,
                'unit_price_estimate' => '50.00',
                'currency' => 'AED',
                'price_source' => 'STANDARD',
                'product_name_ar' => 'منتج',
                'product_name_en' => 'Product',
                'treatment_name_ar' => 'خدمة',
                'treatment_name_en' => 'Service',
                'sort_order' => 0,
            ]);

            return [$manager, $pickup, $order->fresh()];
        }

        $payload = [
            'customer_id' => $customer->id,
            'address_id' => $address->id,
            'lines' => [['offering_id' => $offering->id, 'quantity' => 1]],
            'scheduled_pickup_at' => $pickupAt,
            'scheduled_delivery_at' => $deliveryAt,
            'pickup_driver_id' => $pickup->id,
        ];
        if ($deliveryDriver !== null) {
            $payload['delivery_driver_id'] = $deliveryDriver->id;
        }

        $order = app(GhOrderService::class)->create($payload, $manager);

        if ($status === GhOrderStatus::PICKUP && $order->status !== GhOrderStatus::PICKUP) {
            $order->forceFill(['status' => GhOrderStatus::PICKUP])->save();
        }

        return [$manager, $pickup, $order->fresh()];
    }
}
