<?php

namespace Tests\Feature\GeneralHouse;

use App\Models\GeneralHouse\GhCustomer;
use App\Models\GeneralHouse\GhCustomerAddress;
use App\Models\GeneralHouse\GhOrder;
use App\Models\GeneralHouse\GhZone;
use App\Models\User;
use App\Services\GeneralHouse\Orders\GhOrderBoardService;
use App\Support\GeneralHouse\GhOrderBoardTab;
use App\Support\GeneralHouse\GhOrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrderBoardV2Test extends TestCase
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

    public function test_admin_board_shows_six_tabs_and_area_groups(): void
    {
        $manager = User::factory()->manager()->create();
        $driver = User::factory()->driver()->create();
        $marina = GhZone::factory()->create(['name_en' => 'Dubai Marina', 'name_ar' => 'دبي مارينا', 'sort_order' => 1]);
        $barsha = GhZone::factory()->create(['name_en' => 'Al Barsha', 'name_ar' => 'البرشاء', 'sort_order' => 2]);

        $this->makeOrder($marina, $driver, GhOrderStatus::PICKUP, 'GH-ORD-000001');
        $this->makeOrder($marina, $driver, GhOrderStatus::PICKUP, 'GH-ORD-000002');
        $this->makeOrder($barsha, $driver, GhOrderStatus::PICKUP, 'GH-ORD-000003');
        $this->makeOrder($marina, $driver, GhOrderStatus::WORKING, 'GH-ORD-000004');

        $html = $this->actingAs($manager)
            ->get(route('gh.orders.index', ['tab' => 'pickup']))
            ->assertOk()
            ->assertSee(__('gh.board.tabs.later'), false)
            ->assertSee(__('gh.board.tabs.actions'), false)
            ->assertSee(__('gh.board.tabs.pickup'), false)
            ->assertSee(__('gh.board.tabs.working'), false)
            ->assertSee(__('gh.board.tabs.delivery'), false)
            ->assertSee(__('gh.board.tabs.complete'), false)
            ->assertSee('GH-ORD-000001', false)
            ->assertDontSee('GH-ORD-000004', false)
            ->getContent();

        $this->assertStringContainsString('gh-order-card', $html);
        $this->assertStringContainsString('gh-board-area', $html);
        $this->assertStringContainsString('gh-order-board', $html);
        $this->assertStringContainsString('gh-board-btn', $html);
        $this->assertStringContainsString('gh-order-card__meta--3', $html);
        $this->assertStringContainsString('gh-order-card__meta--4', $html);
        $this->assertStringContainsString('gh-order-card__cell--seq', $html);
        $this->assertStringContainsString('gh-order-card__cell--customer', $html);
        $this->assertStringContainsString('gh-order-card__cell--address', $html);
        $this->assertStringContainsString('data-gh-card-upper', $html);
        $this->assertStringContainsString('data-gh-card-lower', $html);
        $this->assertStringContainsString('data-gh-filters-toggle', $html);
        $this->assertStringNotContainsString('<table', $html);

        $board = app(GhOrderBoardService::class)->boardForAdmin(['tab' => GhOrderBoardTab::PICKUP]);
        $this->assertSame(2, count($board['groups']));
        $this->assertSame(2, $board['groups'][0]['count']);
        $this->assertSame(1, $board['groups'][0]['orders'][0]['sequence']);
        $this->assertSame('NORMAL', $board['groups'][0]['orders'][0]['order_type']);
    }

    public function test_driver_sees_only_own_assignments_and_working_is_readonly(): void
    {
        $driverA = User::factory()->driver()->create();
        $driverB = User::factory()->driver()->create();
        $zone = GhZone::factory()->create();

        $mine = $this->makeOrder($zone, $driverA, GhOrderStatus::PICKUP, 'GH-ORD-000010');
        $theirs = $this->makeOrder($zone, $driverB, GhOrderStatus::PICKUP, 'GH-ORD-000011');
        $working = $this->makeOrder($zone, $driverA, GhOrderStatus::WORKING, 'GH-ORD-000012');

        $this->actingAs($driverA)
            ->get(route('gh.driver.dashboard', ['tab' => 'pickup']))
            ->assertOk()
            ->assertSee('GH-ORD-000010', false)
            ->assertDontSee('GH-ORD-000011', false);

        $workingHtml = $this->actingAs($driverA)
            ->get(route('gh.driver.dashboard', ['tab' => 'working']))
            ->assertOk()
            ->assertSee('GH-ORD-000012', false)
            ->assertSee(__('gh.board.working_readonly_hint'), false)
            ->getContent();

        $this->assertStringContainsString('is-readonly', $workingHtml);
        $this->assertSame($mine->pickup_driver_id, $driverA->id);
        $this->assertSame($theirs->pickup_driver_id, $driverB->id);
        $this->assertSame($working->status, GhOrderStatus::WORKING);
    }

    public function test_driver_cannot_see_other_driver_delivery(): void
    {
        $driverA = User::factory()->driver()->create();
        $driverB = User::factory()->driver()->create();
        $zone = GhZone::factory()->create();
        $this->makeOrder($zone, $driverA, GhOrderStatus::DELIVERY, 'GH-ORD-000020', deliveryDriverId: $driverB->id);

        $this->actingAs($driverA)
            ->get(route('gh.driver.dashboard', ['tab' => 'delivery']))
            ->assertOk()
            ->assertDontSee('GH-ORD-000020', false);

        $this->actingAs($driverB)
            ->get(route('gh.driver.dashboard', ['tab' => 'delivery']))
            ->assertOk()
            ->assertSee('GH-ORD-000020', false);
    }

    public function test_arabic_board_labels_render(): void
    {
        $manager = User::factory()->manager()->create(['language_preference' => 'ar']);
        app()->setLocale('ar');

        $this->actingAs($manager)
            ->withSession(['locale' => 'ar'])
            ->get(route('gh.orders.index', ['tab' => 'pickup']))
            ->assertOk()
            ->assertSee('استلام', false)
            ->assertSee('لاحقاً', false);
    }

    public function test_card_has_exact_three_and_four_metadata_groups(): void
    {
        $manager = User::factory()->manager()->create();
        $driver = User::factory()->driver()->create();
        $zone = GhZone::factory()->create(['name_en' => 'Jumeirah', 'name_ar' => 'جميرا']);
        $customer = GhCustomer::factory()->create([
            'name_en' => 'Very Long Customer Name For Ellipsis Coverage ABCDEFG',
            'name_ar' => 'اسم عميل طويل جداً لاختبار الاقتصاص في الشبكة',
        ]);
        $address = GhCustomerAddress::factory()->create([
            'customer_id' => $customer->id,
            'zone_id' => $zone->id,
            'building' => 'Tower 12 Building Extra Long Address Line',
            'street' => 'Sheikh Zayed Road Extension Segment',
            'area' => 'Jumeirah',
        ]);

        GhOrder::factory()->create([
            'reference' => 'GH-ORD-777001',
            'customer_id' => $customer->id,
            'address_id' => $address->id,
            'zone_id' => $zone->id,
            'pickup_driver_id' => $driver->id,
            'status' => GhOrderStatus::PICKUP,
            'pickup_starts_at' => Carbon::now('UTC')->subHours(2),
            'pickup_ends_at' => Carbon::now('UTC')->subHour(),
        ]);

        app()->setLocale('en');
        $html = $this->actingAs($manager)
            ->withSession(['locale' => 'en'])
            ->get(route('gh.orders.index', ['tab' => 'pickup']))
            ->assertOk()
            ->assertSee('GH-ORD-777001', false)
            ->assertSee(__('gh.driver.overdue'), false)
            ->assertSee(__('gh.board.more_filters'), false)
            ->getContent();

        $this->assertSame(1, preg_match_all('/data-gh-card-upper/', $html));
        $this->assertSame(1, preg_match_all('/data-gh-card-lower/', $html));
        preg_match('/data-gh-card-upper[^>]*>(.*?)<\/div>\s*<div class="gh-order-card__meta gh-order-card__meta--4"/s', $html, $upper);
        preg_match('/data-gh-card-lower[^>]*>(.*?)<\/div>\s*<footer class="gh-order-card__actions"/s', $html, $lower);
        $this->assertNotEmpty($upper[1] ?? null);
        $this->assertNotEmpty($lower[1] ?? null);
        $this->assertSame(3, preg_match_all('/class="gh-order-card__cell /', $upper[1]));
        $this->assertSame(4, preg_match_all('/class="gh-order-card__cell /', $lower[1]));
        $this->assertStringContainsString('gh-order-card__cell--seq', $upper[1]);
        $this->assertStringContainsString('gh-order-card__cell--customer', $upper[1]);
        $this->assertStringContainsString('gh-order-card__cell--address', $upper[1]);
        $this->assertStringContainsString('gh-order-card__cell--date', $lower[1]);
        $this->assertStringContainsString('gh-order-card__badge--danger', $html);
        $this->assertMatchesRegularExpression('/\d{2}\/\d{2}/', $html);

        $driverHtml = $this->actingAs($driver)
            ->withSession(['locale' => 'en'])
            ->get(route('gh.driver.dashboard', ['tab' => 'pickup']))
            ->assertOk()
            ->assertSee('GH-ORD-777001', false)
            ->assertDontSee(__('gh.board.edit'), false)
            ->assertSee(__('gh.board.execute_pickup'), false)
            ->getContent();
        $this->assertStringContainsString('gh-order-card__meta--3', $driverHtml);
        $this->assertStringContainsString('gh-order-card__meta--4', $driverHtml);
        $this->assertStringContainsString('data-gh-task-action', $driverHtml);
        $this->assertStringContainsString('gh-board-area-list', $driverHtml);

        $filtered = $this->actingAs($manager)
            ->get(route('gh.orders.index', ['tab' => 'pickup', 'zone_id' => $zone->id]))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('gh-board-filters-secondary is-open', $filtered);
    }

    public function test_arabic_and_english_board_keep_grid_markers(): void
    {
        $manager = User::factory()->manager()->create();
        $driver = User::factory()->driver()->create();
        $zone = GhZone::factory()->create();
        $this->makeOrder($zone, $driver, GhOrderStatus::PICKUP, 'GH-ORD-888001');

        app()->setLocale('ar');
        $ar = $this->actingAs($manager)
            ->withSession(['locale' => 'ar'])
            ->get(route('gh.orders.index', ['tab' => 'pickup']))
            ->assertOk()
            ->assertSee('عادي', false)
            ->getContent();
        $this->assertStringContainsString('gh-order-card__meta--3', $ar);
        $this->assertStringContainsString('gh-order-card__meta--4', $ar);

        app()->setLocale('en');
        $en = $this->actingAs($manager)
            ->withSession(['locale' => 'en'])
            ->get(route('gh.orders.index', ['tab' => 'pickup']))
            ->assertOk()
            ->assertSee('Normal', false)
            ->getContent();
        $this->assertStringContainsString('gh-order-card__meta--3', $en);
        $this->assertStringContainsString('gh-order-card__meta--4', $en);
    }

    public function test_driver_sortable_binds_to_area_list_and_reorder_persists_on_board(): void
    {
        $driver = User::factory()->driver()->create();
        $zone = GhZone::factory()->create(['name_en' => 'Reorder Zone', 'sort_order' => 1]);
        $a = $this->makeOrder($zone, $driver, GhOrderStatus::PICKUP, 'GH-ORD-SORT01');
        $b = $this->makeOrder($zone, $driver, GhOrderStatus::PICKUP, 'GH-ORD-SORT02');
        $c = $this->makeOrder($zone, $driver, GhOrderStatus::PICKUP, 'GH-ORD-SORT03');
        $a->forceFill([
            'pickup_starts_at' => Carbon::now('UTC')->addHours(1),
            'pickup_ends_at' => Carbon::now('UTC')->addHours(3),
        ])->save();
        $b->forceFill([
            'pickup_starts_at' => Carbon::now('UTC')->addHours(2),
            'pickup_ends_at' => Carbon::now('UTC')->addHours(4),
        ])->save();
        $c->forceFill([
            'pickup_starts_at' => Carbon::now('UTC')->addHours(3),
            'pickup_ends_at' => Carbon::now('UTC')->addHours(5),
        ])->save();

        $html = $this->actingAs($driver)
            ->get(route('gh.driver.dashboard', ['tab' => 'pickup']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-gh-board-sort-root', $html);
        $this->assertMatchesRegularExpression(
            '/gh-board-area-list"[^>]*data-gh-sortable/',
            $html
        );
        $this->assertStringContainsString('data-sort-item', $html);
        $this->assertStringContainsString('data-sort-handle', $html);

        $this->actingAs($driver)
            ->postJson(route('gh.driver.reorder-pickups'), [
                'order_ids' => [$c->id, $a->id, $b->id],
                'previous_order_ids' => [$a->id, $b->id, $c->id],
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $board = app(GhOrderBoardService::class)->boardForDriver($driver, GhOrderBoardTab::PICKUP, 1);
        $ids = collect($board['groups'])->flatMap(fn ($g) => collect($g['orders'])->pluck('id'))->all();
        $this->assertSame([$c->id, $a->id, $b->id], $ids);

        $reload = $this->actingAs($driver)
            ->get(route('gh.driver.dashboard', ['tab' => 'pickup']))
            ->assertOk()
            ->getContent();
        $posC = strpos($reload, 'GH-ORD-SORT03');
        $posA = strpos($reload, 'GH-ORD-SORT01');
        $posB = strpos($reload, 'GH-ORD-SORT02');
        $this->assertNotFalse($posC);
        $this->assertNotFalse($posA);
        $this->assertNotFalse($posB);
        $this->assertTrue($posC < $posA && $posA < $posB);
    }

    public function test_driver_board_cta_opens_pickup_and_confirm_moves_to_working(): void
    {
        $driver = User::factory()->driver()->create();
        $zone = GhZone::factory()->create();
        $order = $this->makeOrder($zone, $driver, GhOrderStatus::PICKUP, 'GH-ORD-MOVE01');
        $order->forceFill([
            'pickup_starts_at' => Carbon::now('UTC')->subHour(),
            'pickup_ends_at' => Carbon::now('UTC')->addHour(),
        ])->save();

        $boardHtml = $this->actingAs($driver)
            ->get(route('gh.driver.dashboard', ['tab' => 'pickup']))
            ->assertOk()
            ->assertSee('GH-ORD-MOVE01', false)
            ->assertSee(__('gh.board.execute_pickup'), false)
            ->getContent();
        $this->assertStringContainsString('data-gh-task-action', $boardHtml);
        $this->assertStringContainsString(route('gh.driver.pickup', $order, false), $boardHtml);

        $this->actingAs($driver)
            ->get(route('gh.driver.pickup', $order))
            ->assertOk()
            ->assertSee(__('gh.driver.confirm_pickup_clothes'), false);

        $this->actingAs($driver)
            ->post(route('gh.orders.confirm-pickup', $order), [
                'notes' => 'V2.4 board movement restore',
            ])
            ->assertRedirect();

        $this->assertSame(GhOrderStatus::WORKING, $order->fresh()->status);

        $this->actingAs($driver)
            ->get(route('gh.driver.dashboard', ['tab' => 'working']))
            ->assertOk()
            ->assertSee('GH-ORD-MOVE01', false);

        $this->actingAs($driver)
            ->get(route('gh.driver.dashboard', ['tab' => 'pickup']))
            ->assertOk()
            ->assertDontSee('GH-ORD-MOVE01', false);
    }

    public function test_admin_board_paginates_without_hiding_totals(): void
    {
        $manager = User::factory()->manager()->create();
        $driver = User::factory()->driver()->create();
        $zone = GhZone::factory()->create();

        for ($i = 1; $i <= 26; $i++) {
            $this->makeOrder($zone, $driver, GhOrderStatus::PICKUP, sprintf('GH-ORD-%06d', $i));
        }

        app()->setLocale('en');

        $page1 = $this->actingAs($manager)
            ->withSession(['locale' => 'en'])
            ->get(route('gh.orders.index', ['tab' => 'pickup', 'page' => 1]))
            ->assertOk()
            ->assertSee(__('gh.board.next'), false)
            ->assertSee(__('gh.board.pager_range', ['from' => 1, 'to' => 25, 'total' => 26]), false)
            ->getContent();

        $this->assertStringContainsString('GH-ORD-000001', $page1);

        $page2 = $this->actingAs($manager)
            ->withSession(['locale' => 'en'])
            ->get(route('gh.orders.index', ['tab' => 'pickup', 'page' => 2]))
            ->assertOk()
            ->assertSee(__('gh.board.pager_range', ['from' => 26, 'to' => 26, 'total' => 26]), false)
            ->getContent();

        $this->assertStringContainsString('page=1', $page2);

        $board = app(GhOrderBoardService::class)->boardForAdmin(['tab' => GhOrderBoardTab::PICKUP, 'page' => 1]);
        $this->assertSame(26, $board['total']);
        $this->assertSame(26, $board['pager']['total']);
        $this->assertSame(2, $board['pager']['last_page']);
        $this->assertSame(25, collect($board['groups'])->sum('count'));
    }

    private function makeOrder(
        GhZone $zone,
        User $pickupDriver,
        string $status,
        string $reference,
        ?int $deliveryDriverId = null,
    ): GhOrder {
        $customer = GhCustomer::factory()->create();
        $address = GhCustomerAddress::factory()->create([
            'customer_id' => $customer->id,
            'zone_id' => $zone->id,
        ]);

        $order = GhOrder::factory()->create([
            'reference' => $reference,
            'customer_id' => $customer->id,
            'address_id' => $address->id,
            'zone_id' => $zone->id,
            'pickup_driver_id' => $pickupDriver->id,
            'delivery_driver_id' => $deliveryDriverId,
            'status' => $status,
            'pickup_starts_at' => Carbon::now('UTC')->addHour(),
            'pickup_ends_at' => Carbon::now('UTC')->addHours(3),
        ]);

        if ($status === GhOrderStatus::WORKING) {
            $order->forceFill(['picked_up_at' => Carbon::now('UTC')])->save();
        }
        if ($status === GhOrderStatus::DELIVERY) {
            $order->forceFill([
                'picked_up_at' => Carbon::now('UTC')->subDay(),
                'invoice_issued_at' => Carbon::now('UTC')->subHour(),
                'delivery_driver_id' => $deliveryDriverId ?? $pickupDriver->id,
            ])->save();
        }

        return $order->fresh();
    }
}
