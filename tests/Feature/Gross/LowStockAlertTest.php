<?php

namespace Tests\Feature\Gross;

use App\Models\Gross\GrossItem;
use App\Models\User;
use App\Services\Gross\ItemService;
use App\Services\Gross\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LowStockAlertTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_dashboard_shows_low_stock_card_and_sidebar_badge(): void
    {
        $manager = User::factory()->manager()->create();
        $items = app(ItemService::class);
        $stock = app(StockService::class);

        $low = $items->create([
            'name' => 'Alert Item',
            'reorder_level' => 5,
            'status' => GrossItem::STATUS_ACTIVE,
        ]);
        $stock->adjustIn($low, 2, 'PIECE', $manager);

        $ok = $items->create([
            'name' => 'Healthy Item',
            'reorder_level' => 5,
            'status' => GrossItem::STATUS_ACTIVE,
        ]);
        $stock->adjustIn($ok, 50, 'PIECE', $manager);

        $this->withSession(['locale' => 'en'])->actingAs($manager)
            ->get(route('portal.product'))
            ->assertOk()
            ->assertSee(__('gross.low_stock_alert.title'), false)
            ->assertSee('Alert Item', false)
            ->assertDontSee('Healthy Item', false)
            ->assertSee(route('gross.stock.index', ['low_stock' => 1]), false);

        $this->actingAs($manager)
            ->get(route('gross.stock.index'))
            ->assertOk()
            ->assertSee('badge rounded-pill text-bg-warning', false)
            ->assertSee('>1<', false);
    }

    public function test_product_dashboard_hides_card_when_all_stock_ok(): void
    {
        $manager = User::factory()->manager()->create();
        $items = app(ItemService::class);
        $stock = app(StockService::class);

        $item = $items->create([
            'name' => 'Plenty',
            'reorder_level' => 5,
            'status' => GrossItem::STATUS_ACTIVE,
        ]);
        $stock->adjustIn($item, 100, 'PIECE', $manager);

        $this->withSession(['locale' => 'en'])->actingAs($manager)
            ->get(route('portal.product'))
            ->assertOk()
            ->assertDontSee(__('gross.low_stock_alert.title'), false);

        $html = $this->actingAs($manager)
            ->get(route('gross.items.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('badge rounded-pill text-bg-warning', $html);
    }

    public function test_service_dashboard_does_not_show_low_stock_card(): void
    {
        $manager = User::factory()->manager()->create();
        $items = app(ItemService::class);

        $items->create([
            'name' => 'Low Only Product',
            'reorder_level' => 5,
            'status' => GrossItem::STATUS_ACTIVE,
        ]);

        $this->withSession(['locale' => 'en'])->actingAs($manager)
            ->get(route('portal.service'))
            ->assertOk()
            ->assertDontSee(__('gross.low_stock_alert.title'), false);
    }
}
