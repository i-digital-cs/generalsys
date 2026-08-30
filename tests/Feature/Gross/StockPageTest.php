<?php

namespace Tests\Feature\Gross;

use App\Models\Gross\GrossItem;
use App\Models\User;
use App\Services\Gross\ItemService;
use App\Services\Gross\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_page_has_search_and_aligned_adjust_form(): void
    {
        $manager = User::factory()->manager()->create();
        $items = app(ItemService::class);
        $a = $items->create(['name' => 'Alpha', 'reorder_level' => 5, 'status' => GrossItem::STATUS_ACTIVE]);
        $b = $items->create(['name' => 'Beta', 'reorder_level' => 5, 'status' => GrossItem::STATUS_ACTIVE]);

        $html = $this->withSession(['locale' => 'en'])->actingAs($manager)
            ->get(route('gross.stock.index'))
            ->assertOk()
            ->assertSee('name="q"', false)
            ->assertSee($a->code, false)
            ->assertSee($b->code, false)
            ->assertSee('input-group-text', false)
            ->getContent();

        $this->assertStringNotContainsString('stock/import', $html);
        $this->assertStringNotContainsString('stock/template', $html);

        $filtered = $this->actingAs($manager)
            ->get(route('gross.stock.index', ['q' => 'Alpha']))
            ->assertOk();
        $filtered->assertSee('Alpha', false);
        $this->assertSame(1, $filtered->viewData('items')->total());
    }

    public function test_low_stock_filter(): void
    {
        $manager = User::factory()->manager()->create();
        $items = app(ItemService::class);
        $stock = app(StockService::class);

        $low = $items->create(['name' => 'LowOne', 'reorder_level' => 5, 'status' => GrossItem::STATUS_ACTIVE]);
        $ok = $items->create(['name' => 'OkOne', 'reorder_level' => 5, 'status' => GrossItem::STATUS_ACTIVE]);
        $stock->adjustIn($ok, 20, 'PIECE', $manager);

        $response = $this->actingAs($manager)
            ->get(route('gross.stock.index', ['low_stock' => 1]))
            ->assertOk();
        $response->assertSee('LowOne', false);
        $codes = $response->viewData('items')->pluck('name')->all();
        $this->assertContains('LowOne', $codes);
        $this->assertNotContains('OkOne', $codes);
    }

    public function test_movements_ledger_lists_adjustments(): void
    {
        $manager = User::factory()->manager()->create([
            'name_en' => 'Stock Manager',
            'name_ar' => 'مدير مخزون',
        ]);
        $item = app(ItemService::class)->create([
            'name' => 'LedgerItem',
            'status' => GrossItem::STATUS_ACTIVE,
        ]);
        app(StockService::class)->adjustIn($item, 3, 'PIECE', $manager, 'Opening balance');

        $this->withSession(['locale' => 'en'])->actingAs($manager)
            ->get(route('gross.stock.movements'))
            ->assertOk()
            ->assertSee('Stock movements', false)
            ->assertSee($item->code, false)
            ->assertSee('LedgerItem', false)
            ->assertSee('Adjustment in', false)
            ->assertSee('Opening balance', false)
            ->assertSee('Stock Manager', false);

        $filtered = $this->actingAs($manager)
            ->get(route('gross.stock.movements', ['q' => 'LedgerItem', 'type' => 'ADJUSTMENT_IN']))
            ->assertOk();
        $this->assertSame(1, $filtered->viewData('movements')->total());
    }
}
