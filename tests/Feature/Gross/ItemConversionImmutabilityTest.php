<?php

namespace Tests\Feature\Gross;

use App\Models\Gross\GrossItem;
use App\Models\User;
use App\Services\Gross\ItemService;
use App\Services\Gross\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemConversionImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_always_stores_one_piece_per_carton_internally(): void
    {
        $items = app(ItemService::class);
        $stock = app(StockService::class);
        $actor = User::factory()->manager()->create();

        $item = $items->create([
            'name' => 'Piece only',
            'pieces_per_carton' => 99,
            'reorder_level' => 5,
            'status' => GrossItem::STATUS_ACTIVE,
        ]);

        $this->assertSame(1, $item->pieces_per_carton);
        $this->assertTrue($item->isLowStock());

        $stock->adjustIn($item, 10, 'PIECE', $actor);
        $this->assertFalse($item->fresh()->isLowStock());

        $stock->adjustOut($item->fresh(), 5, 'PIECE', $actor);
        $this->assertTrue($item->fresh()->isLowStock());
    }
}
