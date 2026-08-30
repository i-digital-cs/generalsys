<?php

namespace Tests\Unit\Gross;

use App\Exceptions\GrossException;
use App\Models\Gross\GrossItem;
use App\Models\Gross\GrossStockBalance;
use App\Models\Gross\GrossStockMovement;
use App\Models\User;
use App\Services\Gross\ItemService;
use App\Services\Gross\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockServiceTest extends TestCase
{
    use RefreshDatabase;

    private StockService $stock;

    private GrossItem $item;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stock = app(StockService::class);
        $this->item = app(ItemService::class)->create([
            'name' => 'Test Item',
            'pieces_per_carton' => 10,
            'reorder_level' => 5,
            'status' => GrossItem::STATUS_ACTIVE,
        ]);
    }

    public function test_adjust_in_and_out(): void
    {
        $actor = User::factory()->manager()->create();
        $this->stock->adjustIn($this->item, 20, GrossStockMovement::UNIT_PIECE, $actor);
        $balance = GrossStockBalance::query()->where('item_id', $this->item->id)->firstOrFail();
        $this->assertSame(20, $balance->qty_on_hand_pieces);

        $this->stock->adjustOut($this->item, 5, GrossStockMovement::UNIT_PIECE, $actor);
        $balance->refresh();
        $this->assertSame(15, $balance->qty_on_hand_pieces);

        $this->assertSame(2, GrossStockMovement::query()->where('item_id', $this->item->id)->count());
    }

    public function test_adjust_out_cannot_go_negative(): void
    {
        $this->stock->adjustIn($this->item, 3, GrossStockMovement::UNIT_PIECE);

        $this->expectException(GrossException::class);
        $this->stock->adjustOut($this->item, 5, GrossStockMovement::UNIT_PIECE);
    }

    public function test_ensure_balance_row_and_lock_path_sale(): void
    {
        $balance = $this->stock->ensureBalanceRow($this->item);
        $this->assertSame(0, $balance->qty_on_hand_pieces);

        $this->stock->adjustIn($this->item, 10, GrossStockMovement::UNIT_PIECE);
        $this->stock->applySaleOut(
            $this->item,
            4,
            1,
            GrossStockMovement::UNIT_PIECE,
            4,
            'test',
            1,
        );

        $balance->refresh();
        $this->assertSame(6, $balance->qty_on_hand_pieces);
        $this->assertSame(4, $balance->qty_with_courier_pieces);
    }
}
