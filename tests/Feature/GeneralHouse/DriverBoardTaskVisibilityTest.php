<?php

namespace Tests\Feature\GeneralHouse;

use App\Models\GeneralHouse\GhCustomer;
use App\Models\GeneralHouse\GhCustomerAddress;
use App\Models\GeneralHouse\GhOrder;
use App\Models\GeneralHouse\GhZone;
use App\Models\User;
use App\Services\GeneralHouse\Orders\GhOrderBoardService;
use App\Support\GeneralHouse\GhAssignmentResponsibility;
use App\Support\GeneralHouse\GhOrderBoardTab;
use App\Support\GeneralHouse\GhOrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Pickup vs delivery assignment must stay separate on the driver board:
 * All may show historical pickup-linked orders, but Delivery tab / Open Delivery
 * require delivery_driver_id === authenticated driver.
 */
class DriverBoardTaskVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-19 12:00:00', 'Asia/Dubai'));
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

    public function test_pickup_only_driver_sees_delivery_order_in_all_not_delivery_tab(): void
    {
        $pickup = User::factory()->driver()->create();
        $order = $this->makeOrder([
            'status' => GhOrderStatus::DELIVERY,
            'pickup_driver_id' => $pickup->id,
            'delivery_driver_id' => null,
            'picked_up_at' => Carbon::now('UTC')->subHour(),
            'scheduled_delivery_at' => Carbon::now('UTC')->subMinutes(30),
        ]);

        $board = app(GhOrderBoardService::class);
        $all = $board->boardForDriver($pickup, GhOrderBoardTab::ALL_ORDERS, 1);
        $delivery = $board->boardForDriver($pickup, GhOrderBoardTab::DELIVERY, 1);
        $counts = collect($all['tabs'])->pluck('count', 'key');

        $this->assertSame(1, (int) $counts[GhOrderBoardTab::ALL_ORDERS]);
        $this->assertSame(0, (int) $counts[GhOrderBoardTab::DELIVERY]);
        $this->assertSame(1, $all['total']);
        $this->assertSame(0, $delivery['total']);

        $card = collect($all['groups'])->flatMap(fn ($g) => $g['orders'])->firstWhere('id', $order->id);
        $this->assertNotNull($card);
        $this->assertFalse((bool) $card['can_execute_task']);
        $this->assertNull($card['task_action_label']);
        $this->assertTrue((bool) $card['needs_delivery_driver']);
        $this->assertStringContainsString('/driver/pickup/'.$order->id, (string) $card['href']);
        $this->assertStringNotContainsString('/driver/delivery/'.$order->id, (string) $card['href']);

        $html = $this->actingAs($pickup)
            ->withSession(['locale' => 'en'])
            ->get(route('gh.driver.dashboard', ['tab' => 'all']))
            ->assertOk()
            ->assertSee($order->reference, false)
            ->assertSee(__('gh.warnings.needs_delivery_driver'), false)
            ->assertDontSee(__('gh.board.execute_delivery'), false)
            ->getContent();
        $this->assertStringNotContainsString('data-gh-task-action', $html);

        $this->actingAs($pickup)
            ->withSession(['locale' => 'ar'])
            ->get(route('gh.driver.dashboard', ['tab' => 'all']))
            ->assertOk()
            ->assertSee(__('gh.warnings.needs_delivery_driver', [], 'ar'), false)
            ->assertDontSee(__('gh.board.execute_delivery', [], 'ar'), false);

        $this->actingAs($pickup)
            ->get(route('gh.driver.delivery', $order))
            ->assertForbidden();
    }

    public function test_delivery_only_driver_sees_delivery_tab_and_can_open(): void
    {
        $pickup = User::factory()->driver()->create();
        $delivery = User::factory()->driver()->create();
        $order = $this->makeOrder([
            'status' => GhOrderStatus::DELIVERY,
            'pickup_driver_id' => $pickup->id,
            'delivery_driver_id' => $delivery->id,
            'picked_up_at' => Carbon::now('UTC')->subHour(),
            'scheduled_delivery_at' => Carbon::now('UTC')->subMinutes(30),
        ]);

        $board = app(GhOrderBoardService::class);
        $deliveryBoard = $board->boardForDriver($delivery, GhOrderBoardTab::DELIVERY, 1);
        $pickupDeliveryBoard = $board->boardForDriver($pickup, GhOrderBoardTab::DELIVERY, 1);
        $counts = collect($deliveryBoard['tabs'])->pluck('count', 'key');

        $this->assertSame(1, (int) $counts[GhOrderBoardTab::DELIVERY]);
        $this->assertSame(1, $deliveryBoard['total']);
        $this->assertSame(0, $pickupDeliveryBoard['total']);

        $card = collect($deliveryBoard['groups'])->flatMap(fn ($g) => $g['orders'])->firstWhere('id', $order->id);
        $this->assertTrue((bool) $card['can_execute_task']);
        $this->assertSame(__('gh.board.execute_delivery'), $card['task_action_label']);
        $this->assertStringContainsString('/driver/delivery/'.$order->id, (string) $card['href']);

        $this->actingAs($delivery)
            ->get(route('gh.driver.dashboard', ['tab' => 'delivery']))
            ->assertOk()
            ->assertSee($order->reference, false)
            ->assertSee(__('gh.board.execute_delivery'), false);

        $this->actingAs($delivery)
            ->get(route('gh.driver.delivery', $order))
            ->assertOk();

        $this->actingAs($pickup)
            ->get(route('gh.driver.delivery', $order))
            ->assertForbidden();
    }

    public function test_same_driver_assigned_to_both_stages(): void
    {
        $driver = User::factory()->driver()->create();
        $order = $this->makeOrder([
            'status' => GhOrderStatus::DELIVERY,
            'pickup_driver_id' => $driver->id,
            'delivery_driver_id' => $driver->id,
            'picked_up_at' => Carbon::now('UTC')->subHour(),
            'scheduled_delivery_at' => Carbon::now('UTC')->subMinutes(30),
        ]);

        $board = app(GhOrderBoardService::class)->boardForDriver($driver, GhOrderBoardTab::DELIVERY, 1);
        $card = collect($board['groups'])->flatMap(fn ($g) => $g['orders'])->firstWhere('id', $order->id);
        $this->assertTrue((bool) $card['can_execute_task']);
        $this->assertFalse((bool) $card['needs_delivery_driver']);

        $this->actingAs($driver)->get(route('gh.driver.delivery', $order))->assertOk();
    }

    public function test_different_pickup_and_delivery_drivers_split_visibility(): void
    {
        $pickup = User::factory()->driver()->create();
        $delivery = User::factory()->driver()->create();
        $working = $this->makeOrder([
            'status' => GhOrderStatus::WORKING,
            'pickup_driver_id' => $pickup->id,
            'delivery_driver_id' => $delivery->id,
            'picked_up_at' => Carbon::now('UTC')->subHours(2),
            'scheduled_delivery_at' => Carbon::now('UTC')->addHour(),
        ]);
        $ready = $this->makeOrder([
            'status' => GhOrderStatus::DELIVERY,
            'pickup_driver_id' => $pickup->id,
            'delivery_driver_id' => $delivery->id,
            'picked_up_at' => Carbon::now('UTC')->subHours(2),
            'scheduled_delivery_at' => Carbon::now('UTC')->subHour(),
        ]);

        $board = app(GhOrderBoardService::class);
        $pickupAll = $board->boardForDriver($pickup, GhOrderBoardTab::ALL_ORDERS, 1);
        $pickupWorking = $board->boardForDriver($pickup, GhOrderBoardTab::WORKING, 1);
        $pickupDelivery = $board->boardForDriver($pickup, GhOrderBoardTab::DELIVERY, 1);
        $deliveryTab = $board->boardForDriver($delivery, GhOrderBoardTab::DELIVERY, 1);

        $this->assertSame(2, $pickupAll['total']);
        $this->assertSame(1, $pickupWorking['total']);
        $this->assertSame(0, $pickupDelivery['total']);
        $this->assertSame(1, $deliveryTab['total']);

        $pickupAllCard = collect($pickupAll['groups'])->flatMap(fn ($g) => $g['orders'])->firstWhere('id', $ready->id);
        $this->assertFalse((bool) $pickupAllCard['can_execute_task']);
        $this->assertStringNotContainsString('/driver/delivery/'.$ready->id, (string) $pickupAllCard['href']);

        $deliveryCard = collect($deliveryTab['groups'])->flatMap(fn ($g) => $g['orders'])->firstWhere('id', $ready->id);
        $this->assertTrue((bool) $deliveryCard['can_execute_task']);

        $this->assertNotNull(
            collect($pickupWorking['groups'])->flatMap(fn ($g) => $g['orders'])->firstWhere('id', $working->id)
        );
    }

    public function test_working_to_delivery_without_assignee_keeps_delivery_count_zero(): void
    {
        $pickup = User::factory()->driver()->create();
        $order = $this->makeOrder([
            'status' => GhOrderStatus::WORKING,
            'pickup_driver_id' => $pickup->id,
            'delivery_driver_id' => null,
            'picked_up_at' => Carbon::now('UTC')->subHours(2),
            'scheduled_delivery_at' => Carbon::now('UTC')->subHour(),
        ]);

        $board = app(GhOrderBoardService::class);
        $before = collect($board->boardForDriver($pickup, GhOrderBoardTab::WORKING, 1)['tabs'])->pluck('count', 'key');
        $this->assertSame(1, (int) $before[GhOrderBoardTab::WORKING]);
        $this->assertSame(0, (int) $before[GhOrderBoardTab::DELIVERY]);

        $order->forceFill(['status' => GhOrderStatus::DELIVERY])->save();

        $afterAll = $board->boardForDriver($pickup, GhOrderBoardTab::ALL_ORDERS, 1);
        $afterDelivery = $board->boardForDriver($pickup, GhOrderBoardTab::DELIVERY, 1);
        $counts = collect($afterAll['tabs'])->pluck('count', 'key');

        $this->assertSame(1, (int) $counts[GhOrderBoardTab::ALL_ORDERS]);
        $this->assertSame(0, (int) $counts[GhOrderBoardTab::WORKING]);
        $this->assertSame(0, (int) $counts[GhOrderBoardTab::DELIVERY]);
        $this->assertSame(0, $afterDelivery['total']);

        $card = collect($afterAll['groups'])->flatMap(fn ($g) => $g['orders'])->firstWhere('id', $order->id);
        $this->assertFalse((bool) $card['can_execute_task']);
        $this->assertTrue((bool) $card['needs_delivery_driver']);
    }

    public function test_manager_can_assign_different_delivery_driver_on_delivery_status(): void
    {
        $manager = User::factory()->manager()->create();
        $pickup = User::factory()->driver()->create();
        $delivery = User::factory()->driver()->create();
        $order = $this->makeUnassignedDeliveryOrder($pickup);

        $this->assertManagerSeesUnassignedDelivery($manager, $order);
        $this->assertDriverHasHistoricalAllOnly($pickup, $order);

        $this->actingAs($manager)->post(route('gh.orders.reassign-driver', $order), [
            'responsibility' => GhAssignmentResponsibility::DELIVERY,
            'driver_user_id' => $delivery->id,
        ])->assertRedirect(route('gh.orders.show', $order));

        $order->refresh();
        $this->assertSame((int) $pickup->id, (int) $order->pickup_driver_id);
        $this->assertSame((int) $delivery->id, (int) $order->delivery_driver_id);

        $this->assertAssignedDeliveryWorkflow($delivery, $pickup, $order);
    }

    public function test_manager_can_assign_pickup_driver_as_delivery_driver(): void
    {
        $manager = User::factory()->manager()->create();
        $pickup = User::factory()->driver()->create();
        $other = User::factory()->driver()->create();
        $order = $this->makeUnassignedDeliveryOrder($pickup);

        $this->actingAs($manager)->post(route('gh.orders.reassign-driver', $order), [
            'responsibility' => GhAssignmentResponsibility::DELIVERY,
            'driver_user_id' => $pickup->id,
        ])->assertRedirect(route('gh.orders.show', $order));

        $order->refresh();
        $this->assertSame((int) $pickup->id, (int) $order->pickup_driver_id);
        $this->assertSame((int) $pickup->id, (int) $order->delivery_driver_id);

        $board = app(GhOrderBoardService::class);
        $deliveryBoard = $board->boardForDriver($pickup, GhOrderBoardTab::DELIVERY, 1);
        $counts = collect($deliveryBoard['tabs'])->pluck('count', 'key');
        $this->assertSame(1, (int) $counts[GhOrderBoardTab::DELIVERY]);
        $this->assertSame(1, $deliveryBoard['total']);

        $card = collect($deliveryBoard['groups'])->flatMap(fn ($g) => $g['orders'])->firstWhere('id', $order->id);
        $this->assertTrue((bool) $card['can_execute_task']);
        $this->assertSame(__('gh.board.execute_delivery'), $card['task_action_label']);
        $this->assertFalse((bool) $card['needs_delivery_driver']);
        $this->assertStringContainsString('/driver/delivery/'.$order->id, (string) $card['href']);

        $allCard = collect($board->boardForDriver($pickup, GhOrderBoardTab::ALL_ORDERS, 1)['groups'])
            ->flatMap(fn ($g) => $g['orders'])
            ->firstWhere('id', $order->id);
        $this->assertTrue((bool) $allCard['can_execute_task']);
        $this->assertFalse((bool) $allCard['needs_delivery_driver']);

        $this->actingAs($pickup)->get(route('gh.driver.delivery', $order))->assertOk();
        $this->actingAs($other)->get(route('gh.driver.delivery', $order))->assertForbidden();

        $this->actingAs($manager)->get(route('gh.orders.show', $order))
            ->assertOk()
            ->assertDontSee(__('gh.warnings.needs_delivery_driver'), false);
    }

    public function test_driver_cannot_reassign_delivery_driver(): void
    {
        $pickup = User::factory()->driver()->create();
        $delivery = User::factory()->driver()->create();
        $order = $this->makeUnassignedDeliveryOrder($pickup);

        $this->actingAs($pickup)->post(route('gh.orders.reassign-driver', $order), [
            'responsibility' => GhAssignmentResponsibility::DELIVERY,
            'driver_user_id' => $delivery->id,
        ])->assertForbidden();

        $this->assertNull($order->fresh()->delivery_driver_id);
    }

    public function test_arabic_and_english_assignment_cues_after_manager_assign(): void
    {
        $manager = User::factory()->manager()->create();
        $pickup = User::factory()->driver()->create();
        $delivery = User::factory()->driver()->create();
        $order = $this->makeUnassignedDeliveryOrder($pickup);

        $this->actingAs($manager)
            ->withSession(['locale' => 'ar'])
            ->get(route('gh.orders.index', ['tab' => 'delivery']))
            ->assertOk()
            ->assertSee($order->reference, false)
            ->assertSee(__('gh.warnings.needs_delivery_driver', [], 'ar'), false);

        $this->actingAs($manager)->post(route('gh.orders.reassign-driver', $order), [
            'responsibility' => GhAssignmentResponsibility::DELIVERY,
            'driver_user_id' => $delivery->id,
        ])->assertRedirect();

        $this->actingAs($delivery)
            ->withSession(['locale' => 'en'])
            ->get(route('gh.driver.dashboard', ['tab' => 'delivery']))
            ->assertOk()
            ->assertSee($order->reference, false)
            ->assertSee(__('gh.board.execute_delivery', [], 'en'), false)
            ->assertDontSee(__('gh.warnings.needs_delivery_driver', [], 'en'), false);

        $this->actingAs($delivery)
            ->withSession(['locale' => 'ar'])
            ->get(route('gh.driver.dashboard', ['tab' => 'delivery']))
            ->assertOk()
            ->assertSee(__('gh.board.execute_delivery', [], 'ar'), false)
            ->assertDontSee(__('gh.warnings.needs_delivery_driver', [], 'ar'), false);
    }

    private function makeUnassignedDeliveryOrder(User $pickup): GhOrder
    {
        return $this->makeOrder([
            'status' => GhOrderStatus::DELIVERY,
            'pickup_driver_id' => $pickup->id,
            'delivery_driver_id' => null,
            'picked_up_at' => Carbon::now('UTC')->subHour(),
            'scheduled_delivery_at' => Carbon::now('UTC')->subMinutes(30),
        ]);
    }

    private function assertManagerSeesUnassignedDelivery(User $manager, GhOrder $order): void
    {
        $admin = app(GhOrderBoardService::class)->boardForAdmin(['tab' => GhOrderBoardTab::DELIVERY]);
        $card = collect($admin['groups'])->flatMap(fn ($g) => $g['orders'])->firstWhere('id', $order->id);
        $this->assertNotNull($card);
        $this->assertTrue((bool) $card['needs_delivery_driver']);

        $this->actingAs($manager)->get(route('gh.orders.show', $order))
            ->assertOk()
            ->assertSee(__('gh.warnings.needs_delivery_driver'), false)
            ->assertSee(__('gh.orders.delivery_unassigned_hint'), false);
    }

    private function assertDriverHasHistoricalAllOnly(User $pickup, GhOrder $order): void
    {
        $board = app(GhOrderBoardService::class);
        $all = $board->boardForDriver($pickup, GhOrderBoardTab::ALL_ORDERS, 1);
        $delivery = $board->boardForDriver($pickup, GhOrderBoardTab::DELIVERY, 1);
        $counts = collect($all['tabs'])->pluck('count', 'key');

        $this->assertSame(1, (int) $counts[GhOrderBoardTab::ALL_ORDERS]);
        $this->assertSame(0, (int) $counts[GhOrderBoardTab::DELIVERY]);
        $this->assertSame(0, $delivery['total']);

        $card = collect($all['groups'])->flatMap(fn ($g) => $g['orders'])->firstWhere('id', $order->id);
        $this->assertFalse((bool) $card['can_execute_task']);
        $this->assertTrue((bool) $card['needs_delivery_driver']);
        $this->assertStringNotContainsString('/driver/delivery/'.$order->id, (string) $card['href']);
    }

    private function assertAssignedDeliveryWorkflow(User $delivery, User $other, GhOrder $order): void
    {
        $board = app(GhOrderBoardService::class);
        $deliveryBoard = $board->boardForDriver($delivery, GhOrderBoardTab::DELIVERY, 1);
        $otherDelivery = $board->boardForDriver($other, GhOrderBoardTab::DELIVERY, 1);
        $counts = collect($deliveryBoard['tabs'])->pluck('count', 'key');

        $this->assertSame(1, (int) $counts[GhOrderBoardTab::DELIVERY]);
        $this->assertSame(1, $deliveryBoard['total']);
        $this->assertSame(0, $otherDelivery['total']);

        $card = collect($deliveryBoard['groups'])->flatMap(fn ($g) => $g['orders'])->firstWhere('id', $order->id);
        $this->assertTrue((bool) $card['can_execute_task']);
        $this->assertSame(__('gh.board.execute_delivery'), $card['task_action_label']);
        $this->assertFalse((bool) $card['needs_delivery_driver']);
        $this->assertStringContainsString('/driver/delivery/'.$order->id, (string) $card['href']);

        $this->actingAs($delivery)
            ->get(route('gh.driver.dashboard', ['tab' => 'delivery']))
            ->assertOk()
            ->assertSee($order->reference, false)
            ->assertSee(__('gh.board.execute_delivery'), false)
            ->assertSee('data-gh-task-action', false);

        $this->actingAs($delivery)->get(route('gh.driver.delivery', $order))->assertOk();
        $this->actingAs($other)->get(route('gh.driver.delivery', $order))->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeOrder(array $overrides): GhOrder
    {
        $tz = (string) config('general_house.timezone', 'Asia/Dubai');
        $zone = GhZone::factory()->create();
        $customer = GhCustomer::factory()->create();
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
            'pickup_starts_at' => Carbon::now($tz)->subHours(4),
            'pickup_ends_at' => Carbon::now($tz)->subHours(2),
            'created_by' => $manager->id,
        ], $overrides));
    }
}
