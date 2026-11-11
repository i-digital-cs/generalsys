<?php

namespace Tests\Feature\GeneralHouse;

use App\Models\GeneralHouse\GhCustomer;
use App\Models\GeneralHouse\GhCustomerAddress;
use App\Models\GeneralHouse\GhOrder;
use App\Models\GeneralHouse\GhZone;
use App\Models\User;
use App\Services\GeneralHouse\Orders\GhDriverMovementLogService;
use App\Services\GeneralHouse\Orders\GhOrderAssignmentService;
use App\Support\GeneralHouse\GhCustomerType;
use App\Support\GeneralHouse\GhDriverMovementStatus;
use App\Support\GeneralHouse\GhDriverMovementType;
use App\Support\GeneralHouse\GhOrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DriverMovementLogTest extends TestCase
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

    public function test_same_driver_sees_two_separate_movements_for_one_order(): void
    {
        $driver = User::factory()->driver()->create();
        $order = $this->makeOrder([
            'status' => GhOrderStatus::COMPLETED,
            'pickup_driver_id' => $driver->id,
            'delivery_driver_id' => $driver->id,
            'picked_up_at' => Carbon::now('UTC')->subHours(5),
            'delivered_at' => Carbon::now('UTC')->subHour(),
            'invoice_issued_at' => Carbon::now('UTC')->subHours(2),
        ]);

        $movements = app(GhDriverMovementLogService::class)->movementsForDriver($driver);
        $this->assertCount(2, $movements);
        $this->assertSame(
            [GhDriverMovementType::PICKUP, GhDriverMovementType::DELIVERY],
            $movements->pluck('type')->all()
        );
        $this->assertTrue($movements->every(fn ($m) => $m->order->id === $order->id));
        $this->assertNotSame($movements[0]->key(), $movements[1]->key());

        $html = $this->actingAs($driver)
            ->withSession(['locale' => 'en'])
            ->get(route('gh.driver.history'))
            ->assertOk()
            ->assertSee(__('gh.driver.movement_type.PICKUP'), false)
            ->assertSee(__('gh.driver.movement_type.DELIVERY'), false)
            ->assertDontSee('Pickup + Delivery', false)
            ->assertDontSee('استلام + تسليم', false)
            ->getContent();

        $this->assertSame(1, substr_count($html, 'data-movement-type="PICKUP"'));
        $this->assertSame(1, substr_count($html, 'data-movement-type="DELIVERY"'));
        $this->assertSame(2, substr_count($html, 'data-movement-key="'.$order->id.':'));
    }

    public function test_different_drivers_see_only_own_movement(): void
    {
        $pickup = User::factory()->driver()->create();
        $delivery = User::factory()->driver()->create();
        $order = $this->makeOrder([
            'status' => GhOrderStatus::COMPLETED,
            'pickup_driver_id' => $pickup->id,
            'delivery_driver_id' => $delivery->id,
            'picked_up_at' => Carbon::now('UTC')->subHours(4),
            'delivered_at' => Carbon::now('UTC')->subHour(),
            'invoice_issued_at' => Carbon::now('UTC')->subHours(2),
        ]);

        $pickupMoves = app(GhDriverMovementLogService::class)->movementsForDriver($pickup);
        $this->assertCount(1, $pickupMoves);
        $this->assertSame(GhDriverMovementType::PICKUP, $pickupMoves->first()->type);

        $deliveryMoves = app(GhDriverMovementLogService::class)->movementsForDriver($delivery);
        $this->assertCount(1, $deliveryMoves);
        $this->assertSame(GhDriverMovementType::DELIVERY, $deliveryMoves->first()->type);

        $this->actingAs($pickup)->get(route('gh.driver.history'))
            ->assertOk()
            ->assertSee('data-movement-type="PICKUP"', false)
            ->assertDontSee('data-movement-type="DELIVERY"', false);

        $this->actingAs($delivery)->get(route('gh.driver.history'))
            ->assertOk()
            ->assertSee('data-movement-type="DELIVERY"', false)
            ->assertDontSee('data-movement-type="PICKUP"', false);

        $this->assertSame($order->id, $pickupMoves->first()->order->id);
        $this->assertSame($order->id, $deliveryMoves->first()->order->id);
    }

    public function test_pickup_only_and_delivery_only_orders(): void
    {
        $pickup = User::factory()->driver()->create();
        $delivery = User::factory()->driver()->create();

        $pickupOnly = $this->makeOrder([
            'status' => GhOrderStatus::WORKING,
            'pickup_driver_id' => $pickup->id,
            'delivery_driver_id' => null,
            'picked_up_at' => Carbon::now('UTC')->subHour(),
        ]);

        $deliveryOnly = $this->makeOrder([
            'status' => GhOrderStatus::DELIVERY,
            'pickup_driver_id' => null,
            'delivery_driver_id' => $delivery->id,
            'invoice_issued_at' => Carbon::now('UTC')->subHour(),
        ]);

        $pickupMoves = app(GhDriverMovementLogService::class)->movementsForDriver($pickup);
        $this->assertCount(1, $pickupMoves);
        $this->assertSame(GhDriverMovementType::PICKUP, $pickupMoves->first()->type);
        $this->assertSame($pickupOnly->id, $pickupMoves->first()->order->id);

        $deliveryMoves = app(GhDriverMovementLogService::class)->movementsForDriver($delivery);
        $this->assertCount(1, $deliveryMoves);
        $this->assertSame(GhDriverMovementType::DELIVERY, $deliveryMoves->first()->type);
        $this->assertSame($deliveryOnly->id, $deliveryMoves->first()->order->id);
    }

    public function test_filter_by_type_and_chronological_sort(): void
    {
        $driver = User::factory()->driver()->create();
        $tz = config('general_house.timezone', 'Asia/Dubai');

        $earlier = $this->makeOrder([
            'status' => GhOrderStatus::COMPLETED,
            'pickup_driver_id' => $driver->id,
            'delivery_driver_id' => $driver->id,
            'pickup_starts_at' => Carbon::now($tz)->subDays(2)->setTime(9, 0),
            'pickup_ends_at' => Carbon::now($tz)->subDays(2)->setTime(11, 0),
            'picked_up_at' => Carbon::now('UTC')->subDays(2),
            'invoice_issued_at' => Carbon::now('UTC')->subDays(2)->addHours(3),
            'delivered_at' => Carbon::now('UTC')->subDays(2)->addHours(5),
        ]);
        $later = $this->makeOrder([
            'status' => GhOrderStatus::COMPLETED,
            'pickup_driver_id' => $driver->id,
            'delivery_driver_id' => $driver->id,
            'pickup_starts_at' => Carbon::now($tz)->subDay()->setTime(9, 0),
            'pickup_ends_at' => Carbon::now($tz)->subDay()->setTime(11, 0),
            'picked_up_at' => Carbon::now('UTC')->subDay(),
            'invoice_issued_at' => Carbon::now('UTC')->subDay()->addHours(3),
            'delivered_at' => Carbon::now('UTC')->subDay()->addHours(5),
        ]);

        $all = app(GhDriverMovementLogService::class)->movementsForDriver($driver);
        $this->assertCount(4, $all);
        $this->assertSame($earlier->id, $all[0]->order->id);
        $this->assertSame(GhDriverMovementType::PICKUP, $all[0]->type);
        $this->assertSame($earlier->id, $all[1]->order->id);
        $this->assertSame(GhDriverMovementType::DELIVERY, $all[1]->type);
        $this->assertSame($later->id, $all[2]->order->id);
        $this->assertSame(GhDriverMovementType::PICKUP, $all[2]->type);

        $pickupsOnly = app(GhDriverMovementLogService::class)->movementsForDriver($driver, [
            'type' => GhDriverMovementType::PICKUP,
        ]);
        $this->assertCount(2, $pickupsOnly);
        $this->assertTrue($pickupsOnly->every(fn ($m) => $m->type === GhDriverMovementType::PICKUP));
    }

    public function test_dashboard_recent_completed_lists_two_cards_for_same_driver_order(): void
    {
        $driver = User::factory()->driver()->create();
        $order = $this->makeOrder([
            'status' => GhOrderStatus::COMPLETED,
            'pickup_driver_id' => $driver->id,
            'delivery_driver_id' => $driver->id,
            'picked_up_at' => Carbon::now('UTC')->subHours(3),
            'invoice_issued_at' => Carbon::now('UTC')->subHours(2),
            'delivered_at' => Carbon::now('UTC')->subHour(),
        ]);

        // Board Complete tab shows one order card; movement log keeps separate PICKUP/DELIVERY rows.
        $this->actingAs($driver)->get(route('gh.driver.dashboard', ['tab' => 'complete']))
            ->assertOk()
            ->assertSee($order->reference, false)
            ->assertSee('gh-order-card', false);

        $html = $this->actingAs($driver)->get(route('gh.driver.history'))->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, 'data-movement-key="'.$order->id.':PICKUP"'));
        $this->assertSame(1, substr_count($html, 'data-movement-key="'.$order->id.':DELIVERY"'));
        $this->assertDontSeeString('Pickup + Delivery', $html);
    }

    public function test_reassignment_history_preserved_on_movement(): void
    {
        $manager = User::factory()->manager()->create();
        $first = User::factory()->driver()->create();
        $second = User::factory()->driver()->create();
        $order = $this->makeOrder([
            'status' => GhOrderStatus::PICKUP,
            'pickup_driver_id' => $first->id,
        ]);

        app(GhOrderAssignmentService::class)->assignPickup($order, $first->id, $manager);
        app(GhOrderAssignmentService::class)->assignPickup($order->fresh(), $second->id, $manager);

        $firstMoves = app(GhDriverMovementLogService::class)->movementsForDriver($first);
        $this->assertCount(1, $firstMoves);
        $this->assertSame(GhDriverMovementType::PICKUP, $firstMoves->first()->type);
        $this->assertGreaterThanOrEqual(2, count($firstMoves->first()->assignmentHistory));

        $secondMoves = app(GhDriverMovementLogService::class)->movementsForDriver($second->fresh());
        $this->assertCount(1, $secondMoves);
        $this->assertSame(GhDriverMovementStatus::READY, $secondMoves->first()->status);
    }

    public function test_order_reference_search_full_numeric_partial_and_filters(): void
    {
        $driver = User::factory()->driver()->create();
        $other = User::factory()->driver()->create();
        $tz = config('general_house.timezone', 'Asia/Dubai');

        $target = $this->makeOrder([
            'reference' => 'GH-ORD-000020',
            'status' => GhOrderStatus::COMPLETED,
            'pickup_driver_id' => $driver->id,
            'delivery_driver_id' => $driver->id,
            'pickup_starts_at' => Carbon::parse('2026-11-08 09:00', $tz),
            'pickup_ends_at' => Carbon::parse('2026-11-08 11:00', $tz),
            'picked_up_at' => Carbon::parse('2026-11-08 10:00', $tz)->utc(),
            'invoice_issued_at' => Carbon::parse('2026-11-08 12:00', $tz)->utc(),
            'delivered_at' => Carbon::parse('2026-11-08 16:00', $tz)->utc(),
        ]);
        $noise = $this->makeOrder([
            'reference' => 'GH-ORD-000099',
            'status' => GhOrderStatus::COMPLETED,
            'pickup_driver_id' => $driver->id,
            'delivery_driver_id' => $driver->id,
            'picked_up_at' => Carbon::now('UTC')->subDay(),
            'invoice_issued_at' => Carbon::now('UTC')->subDay()->addHours(2),
            'delivered_at' => Carbon::now('UTC')->subDay()->addHours(4),
        ]);
        $foreign = $this->makeOrder([
            'reference' => 'GH-ORD-000021',
            'status' => GhOrderStatus::COMPLETED,
            'pickup_driver_id' => $other->id,
            'delivery_driver_id' => $other->id,
            'picked_up_at' => Carbon::now('UTC')->subHours(3),
            'invoice_issued_at' => Carbon::now('UTC')->subHours(2),
            'delivered_at' => Carbon::now('UTC')->subHour(),
        ]);

        $svc = app(GhDriverMovementLogService::class);

        foreach (['GH-ORD-000020', '000020'] as $q) {
            $found = $svc->movementsForDriver($driver, ['q' => $q]);
            $this->assertCount(2, $found, "q={$q}");
            $this->assertTrue($found->every(fn ($m) => $m->order->id === $target->id));
            $this->assertSame(
                [GhDriverMovementType::PICKUP, GhDriverMovementType::DELIVERY],
                $found->pluck('type')->all()
            );
        }

        $partial = $svc->movementsForDriver($driver, ['q' => 'GH-ORD-000']);
        $this->assertTrue($partial->contains(fn ($m) => $m->order->id === $target->id));
        $this->assertTrue($partial->contains(fn ($m) => $m->order->id === $noise->id));
        $this->assertGreaterThanOrEqual(2, $partial->where('order.id', $target->id)->count());

        $numeric = $svc->movementsForDriver($driver, ['q' => '000020']);
        $this->assertFalse($numeric->contains(fn ($m) => $m->order->id === $noise->id));

        $pickupOnly = $svc->movementsForDriver($driver, [
            'q' => '000020',
            'type' => GhDriverMovementType::PICKUP,
            'status' => GhDriverMovementStatus::COMPLETED,
            'date_from' => '2026-11-08',
            'date_to' => '2026-11-08',
        ]);
        $this->assertCount(1, $pickupOnly);
        $this->assertSame(GhDriverMovementType::PICKUP, $pickupOnly->first()->type);

        $this->actingAs($driver)
            ->withSession(['locale' => 'en'])
            ->get(route('gh.driver.history', ['q' => 'GH-ORD-000020']))
            ->assertOk()
            ->assertSee('GH-ORD-000020', false)
            ->assertSee(__('gh.driver.clear_search'), false)
            ->assertSee('data-movement-type="PICKUP"', false)
            ->assertSee('data-movement-type="DELIVERY"', false)
            ->assertDontSee('GH-ORD-000099', false);

        // Other driver cannot see this driver's order via search.
        $this->actingAs($other)
            ->get(route('gh.driver.history', ['q' => 'GH-ORD-000020']))
            ->assertOk()
            ->assertSee(__('gh.driver.empty_search'), false)
            ->assertDontSee('data-movement-key="'.$target->id.':', false);
        $this->assertSame('GH-ORD-000021', $foreign->reference);

        $this->actingAs($driver)
            ->get(route('gh.driver.history', ['q' => 'GH-ORD-999999']))
            ->assertOk()
            ->assertSee(__('gh.driver.empty_search'), false);
    }

    private function assertDontSeeString(string $needle, string $haystack): void
    {
        $this->assertStringNotContainsString($needle, $haystack);
    }

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
            'pickup_starts_at' => Carbon::now($tz)->setTime(10, 0),
            'pickup_ends_at' => Carbon::now($tz)->setTime(12, 0),
            'created_by' => $manager->id,
        ], $overrides));
    }
}
