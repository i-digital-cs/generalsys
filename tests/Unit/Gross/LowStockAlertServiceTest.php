<?php

namespace Tests\Unit\Gross;

use App\Models\Gross\GrossItem;
use App\Models\User;
use App\Services\Gross\ItemService;
use App\Services\Gross\LowStockAlertService;
use App\Services\Gross\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LowStockAlertServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_counts_only_active_items_at_or_below_reorder_level(): void
    {
        $manager = User::factory()->manager()->create();
        $items = app(ItemService::class);
        $stock = app(StockService::class);
        $alerts = app(LowStockAlertService::class);

        $low = $items->create([
            'name' => 'Low Active',
            'reorder_level' => 5,
            'status' => GrossItem::STATUS_ACTIVE,
        ]);
        $stock->adjustIn($low, 3, 'PIECE', $manager);

        $ok = $items->create([
            'name' => 'Ok Active',
            'reorder_level' => 5,
            'status' => GrossItem::STATUS_ACTIVE,
        ]);
        $stock->adjustIn($ok, 20, 'PIECE', $manager);

        $inactiveLow = $items->create([
            'name' => 'Inactive Low',
            'reorder_level' => 5,
            'status' => GrossItem::STATUS_INACTIVE,
        ]);
        $stock->adjustIn($inactiveLow, 2, 'PIECE', $manager);

        $noBalance = $items->create([
            'name' => 'No Balance',
            'reorder_level' => 5,
            'status' => GrossItem::STATUS_ACTIVE,
        ]);

        $this->assertSame(2, $alerts->count());

        $names = $alerts->items(10)->pluck('name')->all();
        $this->assertContains('Low Active', $names);
        $this->assertContains('No Balance', $names);
        $this->assertNotContains('Ok Active', $names);
        $this->assertNotContains('Inactive Low', $names);
    }
}
