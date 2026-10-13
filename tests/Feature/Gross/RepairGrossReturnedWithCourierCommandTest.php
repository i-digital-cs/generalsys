<?php

namespace Tests\Feature\Gross;

use App\Models\Gross\GrossItem;
use App\Models\Gross\GrossSalesInvoice;
use App\Models\Gross\GrossSalesInvoiceLine;
use App\Models\Gross\GrossShipmentAllocation;
use App\Models\Gross\GrossStockBalance;
use App\Models\Gross\GrossStockMovement;
use App\Models\User;
use App\Services\Gross\ItemService;
use App\Services\Gross\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Task 3 — RISK-GROSS-001 repair command dry-run / --apply safety.
 */
class RepairGrossReturnedWithCourierCommandTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private GrossItem $item;

    private StockService $stock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->manager()->create();
        $this->stock = app(StockService::class);
        $this->item = app(ItemService::class)->create([
            'name' => 'Cmd Repair Item',
            'pieces_per_carton' => 1,
            'reorder_level' => 0,
            'status' => GrossItem::STATUS_ACTIVE,
        ]);
    }

    /**
     * Legacy REFUSED pattern: returned_received == returned, with_courier 0.
     */
    private function makeLegacyRefusedAllocation(int $pending): GrossShipmentAllocation
    {
        $line = GrossSalesInvoiceLine::query()->create([
            'sales_invoice_id' => GrossSalesInvoice::query()->create([
                'invoice_number' => 'MH-CMD-'.uniqid(),
                'status' => GrossSalesInvoice::STATUS_REFUSED,
                'fulfillment_status' => GrossSalesInvoice::FULFILLMENT_WITH_COURIER,
                'currency' => 'EGP',
                'customer_name' => 'Cmd',
                'customer_phone' => '010',
                'subtotal' => 0,
                'discount_amount' => 0,
                'shipping_fee' => 0,
                'total' => 0,
                'net_merchandise' => 0,
                'created_by' => $this->manager->id,
            ])->id,
            'item_id' => $this->item->id,
            'item_code_snapshot' => 'C',
            'item_name_snapshot' => 'C',
            'qty' => $pending,
            'unit' => 'PIECE',
            'conversion_factor_snapshot' => 1,
            'qty_pieces' => $pending,
            'unit_price' => 1,
            'discount_amount' => 0,
            'discount_percent' => 0,
            'line_total' => $pending,
            'returned_amount' => 0,
            'lost_amount' => 0,
            'net_line_total' => $pending,
        ]);

        return GrossShipmentAllocation::query()->create([
            'sales_invoice_line_id' => $line->id,
            'conversion_factor_snapshot' => 1,
            'qty_shipped_pieces' => $pending,
            'qty_delivered_pieces' => 0,
            'qty_returned_pieces' => $pending,
            'qty_returned_received_pieces' => $pending,
            'qty_with_courier_pieces' => 0,
            'qty_lost_pieces' => 0,
        ]);
    }

    public function test_dry_run_is_default_and_does_not_mutate(): void
    {
        $this->stock->adjustIn($this->item, 10, GrossStockMovement::UNIT_PIECE, $this->manager);
        $allocation = $this->makeLegacyRefusedAllocation(4);

        $this->artisan('gross:repair-returned-with-courier')
            ->expectsOutputToContain('Dry-run')
            ->assertSuccessful();

        $balance = GrossStockBalance::query()->where('item_id', $this->item->id)->firstOrFail();
        $this->assertSame(10, $balance->qty_on_hand_pieces);
        $this->assertSame(0, $balance->qty_with_courier_pieces);
        $allocation->refresh();
        $this->assertSame(4, (int) $allocation->qty_returned_received_pieces);
        $this->assertSame(0, (int) $allocation->qty_with_courier_pieces);
        $this->assertSame(
            0,
            GrossStockMovement::query()
                ->where('notes', StockService::REPAIR_RETURNED_WITH_COURIER_NOTE)
                ->count(),
        );
    }

    public function test_apply_requires_explicit_flag_and_repairs(): void
    {
        $this->stock->adjustIn($this->item, 10, GrossStockMovement::UNIT_PIECE, $this->manager);
        $allocation = $this->makeLegacyRefusedAllocation(4);

        $this->artisan('gross:repair-returned-with-courier', ['--apply' => true, '--force' => true])
            ->expectsOutputToContain('APPLY')
            ->assertSuccessful();

        $balance = GrossStockBalance::query()->where('item_id', $this->item->id)->firstOrFail();
        $this->assertSame(6, $balance->qty_on_hand_pieces);
        $this->assertSame(4, $balance->qty_with_courier_pieces);
        $allocation->refresh();
        $this->assertSame(0, (int) $allocation->qty_returned_received_pieces);
        $this->assertSame(4, (int) $allocation->qty_with_courier_pieces);
        $this->assertDatabaseHas('gross_stock_movements', [
            'item_id' => $this->item->id,
            'type' => GrossStockMovement::TYPE_SALE_OUT,
            'qty_pieces' => 4,
            'notes' => StockService::REPAIR_RETURNED_WITH_COURIER_NOTE,
            'ref_type' => GrossShipmentAllocation::class,
            'ref_id' => $allocation->id,
        ]);
    }

    public function test_insufficient_on_hand_skips_and_exits_failure(): void
    {
        $this->stock->adjustIn($this->item, 1, GrossStockMovement::UNIT_PIECE, $this->manager);
        $allocation = $this->makeLegacyRefusedAllocation(5);

        $this->artisan('gross:repair-returned-with-courier', ['--apply' => true, '--force' => true])
            ->expectsOutputToContain('insufficient_on_hand')
            ->assertFailed();

        $balance = GrossStockBalance::query()->where('item_id', $this->item->id)->firstOrFail();
        $this->assertSame(1, $balance->qty_on_hand_pieces);
        $this->assertSame(0, $balance->qty_with_courier_pieces);
        $allocation->refresh();
        $this->assertSame(5, (int) $allocation->qty_returned_received_pieces);
        $this->assertSame(0, (int) $allocation->qty_with_courier_pieces);
    }

    public function test_dry_run_with_insufficient_also_exits_failure(): void
    {
        $this->stock->adjustIn($this->item, 1, GrossStockMovement::UNIT_PIECE, $this->manager);
        $this->makeLegacyRefusedAllocation(5);

        $this->artisan('gross:repair-returned-with-courier')
            ->expectsOutputToContain('insufficient_on_hand')
            ->assertFailed();
    }

    public function test_reapply_is_idempotent_at_command_level(): void
    {
        $this->stock->adjustIn($this->item, 10, GrossStockMovement::UNIT_PIECE, $this->manager);
        $allocation = $this->makeLegacyRefusedAllocation(3);

        $this->artisan('gross:repair-returned-with-courier', ['--apply' => true, '--force' => true])
            ->assertSuccessful();

        $this->artisan('gross:repair-returned-with-courier', ['--apply' => true, '--force' => true])
            ->assertSuccessful();

        $balance = GrossStockBalance::query()->where('item_id', $this->item->id)->firstOrFail();
        $this->assertSame(7, $balance->qty_on_hand_pieces);
        $this->assertSame(3, $balance->qty_with_courier_pieces);
        $this->assertSame(
            1,
            GrossStockMovement::query()
                ->where('notes', StockService::REPAIR_RETURNED_WITH_COURIER_NOTE)
                ->where('ref_id', $allocation->id)
                ->count(),
        );
    }

    public function test_lost_path_unaffected_by_repair_service(): void
    {
        $this->stock->adjustIn($this->item, 6, GrossStockMovement::UNIT_PIECE, $this->manager);
        $this->stock->applySaleOut(
            $this->item,
            6,
            1,
            GrossStockMovement::UNIT_PIECE,
            6,
            'loss-fixture',
            1,
            $this->manager,
        );
        $this->stock->applyLoss(
            $this->item,
            2,
            1,
            'loss-fixture',
            1,
            $this->manager,
        );

        $balance = GrossStockBalance::query()->where('item_id', $this->item->id)->firstOrFail();
        $this->assertSame(0, $balance->qty_on_hand_pieces);
        $this->assertSame(4, $balance->qty_with_courier_pieces);
        $this->assertSame(2, $balance->qty_lost_pieces);

        $this->assertDatabaseHas('gross_stock_movements', [
            'type' => GrossStockMovement::TYPE_LOSS_CONFIRM,
            'qty_pieces' => 2,
        ]);
    }
}
