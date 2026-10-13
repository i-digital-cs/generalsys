<?php

namespace Tests\Feature\Gross;

use App\Exceptions\GrossException;
use App\Models\Gross\GrossItem;
use App\Models\Gross\GrossPurchaseInvoice;
use App\Models\Gross\GrossShippingCompany;
use App\Models\Gross\GrossStockBalance;
use App\Models\Gross\GrossStockMovement;
use App\Models\Gross\GrossSupplier;
use App\Models\User;
use App\Services\Gross\ItemService;
use App\Services\Gross\PurchaseInvoiceService;
use App\Services\Gross\SalesInvoiceService;
use App\Services\Gross\StockService;
use App\Services\Gross\UnitConversion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 5 — PIECE/CARTON conversion, purchase currency lock, StockService SSOT repair path.
 */
class GrossStockIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private StockService $stock;

    private GrossItem $item;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stock = app(StockService::class);
        $this->manager = User::factory()->manager()->create();
        $this->item = app(ItemService::class)->create([
            'name' => 'Integrity Item',
            'pieces_per_carton' => 12,
            'reorder_level' => 0,
            'status' => GrossItem::STATUS_ACTIVE,
        ]);
    }

    public function test_item_create_forces_piece_per_carton_one_for_adjustments(): void
    {
        // Approved product rule (ItemConversionImmutability): catalog items store 1.
        $this->assertSame(1, $this->item->pieces_per_carton);

        $this->stock->adjustIn(
            $this->item,
            2,
            GrossStockMovement::UNIT_CARTON,
            $this->manager,
        );

        $balance = GrossStockBalance::query()->where('item_id', $this->item->id)->firstOrFail();
        $this->assertSame(2, $balance->qty_on_hand_pieces);
    }

    public function test_sale_out_uses_line_conversion_factor_not_catalog_default(): void
    {
        $this->stock->adjustIn($this->item, 48, GrossStockMovement::UNIT_PIECE, $this->manager);

        // Transaction-line carton size (snapshot), not item.pieces_per_carton.
        $this->stock->applySaleOut(
            $this->item,
            24,
            12,
            GrossStockMovement::UNIT_CARTON,
            2,
            'test-line',
            1,
            $this->manager,
        );

        $balance = GrossStockBalance::query()->where('item_id', $this->item->id)->firstOrFail();
        $this->assertSame(24, $balance->qty_on_hand_pieces);
        $this->assertSame(24, $balance->qty_with_courier_pieces);

        $movement = GrossStockMovement::query()
            ->where('item_id', $this->item->id)
            ->where('type', GrossStockMovement::TYPE_SALE_OUT)
            ->latest('id')
            ->firstOrFail();
        $this->assertSame(2, $movement->qty);
        $this->assertSame(12, $movement->conversion_factor);
        $this->assertSame(24, $movement->qty_pieces);
    }

    public function test_rejects_zero_pieces_per_carton_factor(): void
    {
        $this->expectException(GrossException::class);
        UnitConversion::factorFor(GrossStockMovement::UNIT_CARTON, 0);
    }

    public function test_purchase_currency_immutable_after_confirm(): void
    {
        $this->stock->adjustIn($this->item, 10, GrossStockMovement::UNIT_PIECE, $this->manager);

        $supplier = GrossSupplier::query()->create([
            'name' => 'Supplier Lock',
            'phone' => '01001112233',
        ]);

        $purchases = app(PurchaseInvoiceService::class);
        $invoice = $purchases->createDraft($this->manager, [
            'supplier_id' => $supplier->id,
            'supplier_name' => $supplier->name,
            'supplier_phone' => $supplier->phone,
            'currency' => 'EGP',
        ], [[
            'item_id' => $this->item->id,
            'qty' => 2,
            'unit' => 'PIECE',
            'buy_price' => 5,
            'sale_price' => 10,
            'discount_amount' => 0,
            'discount_percent' => 0,
        ]]);

        $purchases->confirm($invoice, $this->manager);

        try {
            $purchases->update($invoice->fresh(), [
                'supplier_id' => $supplier->id,
                'supplier_name' => $supplier->name,
                'supplier_phone' => $supplier->phone,
                'currency' => 'AED',
            ], [[
                'item_id' => $this->item->id,
                'qty' => 2,
                'unit' => 'PIECE',
                'buy_price' => 5,
                'sale_price' => 10,
                'discount_amount' => 0,
                'discount_percent' => 0,
            ]], $this->manager);
            $this->fail('Expected purchase currency immutable');
        } catch (GrossException $e) {
            $this->assertSame('gross.errors.currency_immutable', $e->getMessage());
        }

        $this->assertSame('EGP', $invoice->fresh()->currency);
        $this->assertSame(GrossPurchaseInvoice::STATUS_CONFIRMED, $invoice->fresh()->status);
    }

    /**
     * RISK-GROSS-001 corrective: insufficient ON_HAND rejects; no balance/ledger writes.
     */
    public function test_repair_move_rejects_insufficient_without_writes(): void
    {
        $this->stock->adjustIn($this->item, 3, GrossStockMovement::UNIT_PIECE, $this->manager);
        $beforeMovements = GrossStockMovement::query()->where('item_id', $this->item->id)->count();

        try {
            $this->stock->repairMoveOnHandToWithCourier(
                $this->item,
                5,
                'fixture-alloc',
                1,
                $this->manager,
            );
            $this->fail('Expected insufficient stock');
        } catch (GrossException $e) {
            $this->assertSame('gross.errors.insufficient_stock', $e->getMessage());
        }

        $balance = GrossStockBalance::query()->where('item_id', $this->item->id)->firstOrFail();
        $this->assertSame(3, $balance->qty_on_hand_pieces);
        $this->assertSame(0, $balance->qty_with_courier_pieces);
        $this->assertSame(
            $beforeMovements,
            GrossStockMovement::query()->where('item_id', $this->item->id)->count(),
        );
    }

    /** RISK-GROSS-001 corrective: zero ON_HAND rejects (no phantom WITH_COURIER). */
    public function test_risk_gross_001_zero_on_hand_rejects(): void
    {
        $this->stock->ensureBalanceRow($this->item);
        $balance = GrossStockBalance::query()->where('item_id', $this->item->id)->firstOrFail();
        $this->assertSame(0, $balance->qty_on_hand_pieces);

        $this->expectException(GrossException::class);
        $this->expectExceptionMessage('gross.errors.insufficient_stock');
        $this->stock->repairMoveOnHandToWithCourier(
            $this->item,
            7,
            'fixture-alloc',
            2,
            $this->manager,
        );
    }

    public function test_repair_rejects_when_balance_missing_or_empty(): void
    {
        $orphan = app(ItemService::class)->create([
            'name' => 'No Balance Yet',
            'pieces_per_carton' => 1,
            'reorder_level' => 0,
            'status' => GrossItem::STATUS_ACTIVE,
        ]);
        GrossStockBalance::query()->where('item_id', $orphan->id)->delete();

        try {
            $this->stock->repairMoveOnHandToWithCourier($orphan, 2, 'fixture-alloc', 3);
            $this->fail('Expected insufficient stock');
        } catch (GrossException $e) {
            $this->assertSame('gross.errors.insufficient_stock', $e->getMessage());
        }

        // ensureBalanceRow inside the rejected transaction is rolled back.
        $this->assertSame(
            0,
            (int) (GrossStockBalance::query()->where('item_id', $orphan->id)->value('qty_with_courier_pieces') ?? 0),
        );
        $this->assertSame(
            0,
            GrossStockMovement::query()
                ->where('item_id', $orphan->id)
                ->where('notes', StockService::REPAIR_RETURNED_WITH_COURIER_NOTE)
                ->count(),
        );
    }

    public function test_repair_move_decrements_on_hand_when_sufficient(): void
    {
        $this->stock->adjustIn($this->item, 10, GrossStockMovement::UNIT_PIECE, $this->manager);
        $movement = $this->stock->repairMoveOnHandToWithCourier(
            $this->item,
            4,
            'fixture-alloc',
            4,
            $this->manager,
        );

        $balance = GrossStockBalance::query()->where('item_id', $this->item->id)->firstOrFail();
        $this->assertSame(6, $balance->qty_on_hand_pieces);
        $this->assertSame(4, $balance->qty_with_courier_pieces);
        $this->assertSame(GrossStockMovement::TYPE_SALE_OUT, $movement->type);
        $this->assertSame(StockService::REPAIR_RETURNED_WITH_COURIER_NOTE, $movement->notes);
    }

    public function test_duplicate_confirm_and_hand_to_courier_are_idempotent(): void
    {
        $this->stock->adjustIn($this->item, 20, GrossStockMovement::UNIT_PIECE, $this->manager);

        $sales = app(SalesInvoiceService::class);
        $shipping = GrossShippingCompany::query()->create([
            'name' => 'Integrity Ship',
            'status' => GrossShippingCompany::STATUS_ACTIVE,
        ]);

        $invoice = $sales->createDraft($this->manager, [
            'customer_name' => 'Buyer',
            'customer_phone' => '01000000001',
            'currency' => 'EGP',
            'shipping_company_id' => $shipping->id,
            'shipping_fee' => 0,
        ], [[
            'item_id' => $this->item->id,
            'qty' => 5,
            'unit' => 'PIECE',
            'unit_price' => 10,
        ]]);

        $sales->confirm($invoice, $this->manager);
        $sales->confirm($invoice->fresh(), $this->manager);

        $balanceAfterConfirm = GrossStockBalance::query()->where('item_id', $this->item->id)->firstOrFail();
        $this->assertSame(20, $balanceAfterConfirm->qty_on_hand_pieces);
        $this->assertSame(0, $balanceAfterConfirm->qty_with_courier_pieces);

        $invoice = $invoice->fresh();
        $invoice->update([
            'packing_image_path' => 'gross-sales-packing/test.jpg',
            'packing_uploaded_at' => now(),
            'packing_uploaded_by' => $this->manager->id,
        ]);
        $sales->markReadyInWarehouse($invoice->fresh());
        $sales->handToCourier($invoice->fresh(), $this->manager);
        $sales->handToCourier($invoice->fresh(), $this->manager);

        $balance = GrossStockBalance::query()->where('item_id', $this->item->id)->firstOrFail();
        $this->assertSame(15, $balance->qty_on_hand_pieces);
        $this->assertSame(5, $balance->qty_with_courier_pieces);
        $this->assertSame(
            1,
            GrossStockMovement::query()
                ->where('item_id', $this->item->id)
                ->where('type', GrossStockMovement::TYPE_SALE_OUT)
                ->count(),
        );
    }
}
