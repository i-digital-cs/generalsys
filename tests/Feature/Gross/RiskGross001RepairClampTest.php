<?php

namespace Tests\Feature\Gross;

use App\Exceptions\GrossException;
use App\Models\Gross\GrossItem;
use App\Models\Gross\GrossSalesInvoice;
use App\Models\Gross\GrossSalesInvoiceLine;
use App\Models\Gross\GrossShipmentAllocation;
use App\Models\Gross\GrossShippingCompany;
use App\Models\Gross\GrossStockBalance;
use App\Models\Gross\GrossStockMovement;
use App\Models\User;
use App\Services\Gross\AllocationService;
use App\Services\Gross\ItemService;
use App\Services\Gross\SalesInvoiceService;
use App\Services\Gross\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Task 3 — RISK-GROSS-001 corrective regression (was characterization of legacy clamp).
 *
 * Rationale: owner approved Option A — strict conservation + SALE_OUT ledger.
 * Short-path inflation expectations are inverted to assert throw/no-write.
 * Isolated RefreshDatabase fixtures — does not run repair against shared/prod data.
 */
class RiskGross001RepairClampTest extends TestCase
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
            'name' => 'Risk001 Item',
            'pieces_per_carton' => 1,
            'reorder_level' => 0,
            'status' => GrossItem::STATUS_ACTIVE,
        ]);
    }

    private function balance(): GrossStockBalance
    {
        return GrossStockBalance::query()->where('item_id', $this->item->id)->firstOrFail();
    }

    private function totalTracked(): int
    {
        $b = $this->balance();

        return (int) $b->qty_on_hand_pieces
            + (int) $b->qty_with_courier_pieces
            + (int) $b->qty_lost_pieces;
    }

    private function makeAllocation(int $pending = 5): GrossShipmentAllocation
    {
        $line = GrossSalesInvoiceLine::query()->create([
            'sales_invoice_id' => GrossSalesInvoice::query()->create([
                'invoice_number' => 'MH-RISK-'.uniqid(),
                'status' => GrossSalesInvoice::STATUS_REFUSED,
                'fulfillment_status' => GrossSalesInvoice::FULFILLMENT_WITH_COURIER,
                'currency' => 'EGP',
                'customer_name' => 'X',
                'customer_phone' => '010',
                'subtotal' => 0,
                'discount_amount' => 0,
                'shipping_fee' => 0,
                'total' => 0,
                'net_merchandise' => 0,
                'created_by' => $this->manager->id,
            ])->id,
            'item_id' => $this->item->id,
            'item_code_snapshot' => 'X',
            'item_name_snapshot' => 'X',
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

    private function repair(int $pieces, ?GrossShipmentAllocation $allocation = null): GrossStockMovement
    {
        $allocation ??= $this->makeAllocation(max($pieces, 1));

        return $this->stock->repairMoveOnHandToWithCourier(
            $this->item,
            $pieces,
            GrossShipmentAllocation::class,
            (int) $allocation->id,
            $this->manager,
        );
    }

    /** Canonical handover: conservation holds. */
    public function test_apply_sale_out_conserves_total_stock(): void
    {
        $this->stock->adjustIn($this->item, 10, GrossStockMovement::UNIT_PIECE, $this->manager);
        $this->assertSame(10, $this->totalTracked());

        $this->stock->applySaleOut(
            $this->item,
            4,
            1,
            GrossStockMovement::UNIT_PIECE,
            4,
            'fixture',
            1,
            $this->manager,
        );

        $b = $this->balance();
        $this->assertSame(6, $b->qty_on_hand_pieces);
        $this->assertSame(4, $b->qty_with_courier_pieces);
        $this->assertSame(10, $this->totalTracked());
        $this->assertDatabaseHas('gross_stock_movements', [
            'item_id' => $this->item->id,
            'type' => GrossStockMovement::TYPE_SALE_OUT,
            'qty_pieces' => 4,
        ]);
    }

    /** Corrective: sufficient ON_HAND transfers and writes SALE_OUT repair note. */
    public function test_repair_sufficient_on_hand_transfers_with_ledger(): void
    {
        $this->stock->adjustIn($this->item, 10, GrossStockMovement::UNIT_PIECE, $this->manager);
        $movementsBefore = GrossStockMovement::query()->where('item_id', $this->item->id)->count();

        $movement = $this->repair(4);

        $b = $this->balance();
        $this->assertSame(6, $b->qty_on_hand_pieces);
        $this->assertSame(4, $b->qty_with_courier_pieces);
        $this->assertSame(10, $this->totalTracked());
        $this->assertSame($movementsBefore + 1, GrossStockMovement::query()->where('item_id', $this->item->id)->count());
        $this->assertSame(GrossStockMovement::TYPE_SALE_OUT, $movement->type);
        $this->assertSame(StockService::REPAIR_RETURNED_WITH_COURIER_NOTE, $movement->notes);
        $this->assertSame(GrossShipmentAllocation::class, $movement->ref_type);
    }

    public function test_repair_insufficient_on_hand_rejects_without_writes(): void
    {
        $this->stock->adjustIn($this->item, 3, GrossStockMovement::UNIT_PIECE, $this->manager);
        $this->assertSame(3, $this->totalTracked());
        $movementsBefore = GrossStockMovement::query()->where('item_id', $this->item->id)->count();

        try {
            $this->repair(5);
            $this->fail('Expected insufficient stock');
        } catch (GrossException $e) {
            $this->assertSame('gross.errors.insufficient_stock', $e->getMessage());
        }

        $b = $this->balance();
        $this->assertSame(3, $b->qty_on_hand_pieces);
        $this->assertSame(0, $b->qty_with_courier_pieces);
        $this->assertSame(3, $this->totalTracked());
        $this->assertSame($movementsBefore, GrossStockMovement::query()->where('item_id', $this->item->id)->count());
    }

    public function test_repair_zero_on_hand_rejects(): void
    {
        $this->stock->ensureBalanceRow($this->item);

        $this->expectException(GrossException::class);
        $this->expectExceptionMessage('gross.errors.insufficient_stock');
        $this->repair(4);
    }

    public function test_repair_invalid_quantity_rejects(): void
    {
        $this->stock->adjustIn($this->item, 5, GrossStockMovement::UNIT_PIECE, $this->manager);

        $this->expectException(GrossException::class);
        $this->expectExceptionMessage('gross.errors.invalid_qty');
        $this->repair(0);
    }

    public function test_duplicate_repair_same_ref_is_idempotent(): void
    {
        $this->stock->adjustIn($this->item, 10, GrossStockMovement::UNIT_PIECE, $this->manager);
        $allocation = $this->makeAllocation(3);

        $first = $this->repair(3, $allocation);
        $second = $this->repair(3, $allocation);

        $this->assertSame($first->id, $second->id);
        $b = $this->balance();
        $this->assertSame(7, $b->qty_on_hand_pieces);
        $this->assertSame(3, $b->qty_with_courier_pieces);
        $this->assertSame(10, $this->totalTracked());
        $this->assertSame(
            1,
            GrossStockMovement::query()
                ->where('item_id', $this->item->id)
                ->where('type', GrossStockMovement::TYPE_SALE_OUT)
                ->where('notes', StockService::REPAIR_RETURNED_WITH_COURIER_NOTE)
                ->count(),
        );
    }

    public function test_ledger_write_failure_rolls_back_balances(): void
    {
        $this->stock->adjustIn($this->item, 8, GrossStockMovement::UNIT_PIECE, $this->manager);
        $before = $this->balance()->fresh();

        GrossStockMovement::creating(function () {
            throw new \RuntimeException('forced ledger failure');
        });

        try {
            $this->repair(3, $this->makeAllocation(3));
            $this->fail('Expected ledger failure');
        } catch (\RuntimeException $e) {
            $this->assertSame('forced ledger failure', $e->getMessage());
        }

        $after = $this->balance()->fresh();
        $this->assertSame((int) $before->qty_on_hand_pieces, (int) $after->qty_on_hand_pieces);
        $this->assertSame((int) $before->qty_with_courier_pieces, (int) $after->qty_with_courier_pieces);
        $this->assertSame(
            0,
            GrossStockMovement::query()
                ->where('item_id', $this->item->id)
                ->where('notes', StockService::REPAIR_RETURNED_WITH_COURIER_NOTE)
                ->count(),
        );
    }

    /**
     * Sequential contention stand-in: first repair depletes ON_HAND; second cannot overdraw.
     * Parallel first-write same-ref is serialized via allocation+balance locks (Task 3.1).
     */
    public function test_sequential_repairs_cannot_overdraw_on_hand(): void
    {
        $this->stock->adjustIn($this->item, 5, GrossStockMovement::UNIT_PIECE, $this->manager);

        $this->repair(5, $this->makeAllocation(5));

        try {
            $this->repair(5, $this->makeAllocation(5));
            $this->fail('Expected insufficient stock on second repair');
        } catch (GrossException $e) {
            $this->assertSame('gross.errors.insufficient_stock', $e->getMessage());
        }

        $b = $this->balance();
        $this->assertSame(0, $b->qty_on_hand_pieces);
        $this->assertSame(5, $b->qty_with_courier_pieces);
        $this->assertSame(5, $this->totalTracked());
    }

    public function test_client_return_and_refuse_do_not_call_repair_path(): void
    {
        $this->stock->adjustIn($this->item, 10, GrossStockMovement::UNIT_PIECE, $this->manager);

        $sales = app(SalesInvoiceService::class);
        $shipping = GrossShippingCompany::query()->create([
            'name' => 'Risk001 Ship',
            'status' => GrossShippingCompany::STATUS_ACTIVE,
        ]);

        $invoice = $sales->createDraft($this->manager, [
            'customer_name' => 'Buyer',
            'customer_phone' => '01000000999',
            'currency' => 'EGP',
            'shipping_company_id' => $shipping->id,
            'shipping_fee' => 0,
        ], [[
            'item_id' => $this->item->id,
            'qty' => 4,
            'unit' => 'PIECE',
            'unit_price' => 10,
        ]]);

        $sales->confirm($invoice, $this->manager);
        $invoice->update([
            'packing_image_path' => 'gross-sales-packing/x.jpg',
            'packing_uploaded_at' => now(),
            'packing_uploaded_by' => $this->manager->id,
        ]);
        $sales->markReadyInWarehouse($invoice->fresh());
        $sales->handToCourier($invoice->fresh(), $this->manager);

        $this->assertSame(6, $this->balance()->qty_on_hand_pieces);
        $this->assertSame(4, $this->balance()->qty_with_courier_pieces);
        $this->assertSame(10, $this->totalTracked());

        $alloc = $invoice->fresh(['lines.allocation'])->lines->first()->allocation;
        app(AllocationService::class)->partialReturn($alloc, 2, $this->manager);

        $this->assertSame(6, $this->balance()->qty_on_hand_pieces);
        $this->assertSame(4, $this->balance()->qty_with_courier_pieces);
        $this->assertSame(10, $this->totalTracked());
        $this->assertDatabaseHas('gross_stock_movements', [
            'type' => GrossStockMovement::TYPE_SALE_RETURN_CLIENT,
            'qty_pieces' => 2,
        ]);

        $sales->refuse($invoice->fresh(), $this->manager);
        $this->assertSame(GrossSalesInvoice::STATUS_REFUSED, $invoice->fresh()->status);
        $this->assertSame(10, $this->totalTracked(), 'Refuse does not invent stock');
        $this->assertSame(
            0,
            GrossStockMovement::query()
                ->where('notes', StockService::REPAIR_RETURNED_WITH_COURIER_NOTE)
                ->count(),
        );
    }

    public function test_command_shaped_repair_rejects_when_on_hand_short(): void
    {
        $this->stock->adjustIn($this->item, 1, GrossStockMovement::UNIT_PIECE, $this->manager);

        $line = GrossSalesInvoiceLine::query()->create([
            'sales_invoice_id' => GrossSalesInvoice::query()->create([
                'invoice_number' => 'MH-RISK-001',
                'status' => GrossSalesInvoice::STATUS_CONFIRMED,
                'fulfillment_status' => GrossSalesInvoice::FULFILLMENT_WITH_COURIER,
                'currency' => 'EGP',
                'customer_name' => 'X',
                'customer_phone' => '010',
                'subtotal' => 0,
                'discount_amount' => 0,
                'shipping_fee' => 0,
                'total' => 0,
                'net_merchandise' => 0,
                'created_by' => $this->manager->id,
            ])->id,
            'item_id' => $this->item->id,
            'item_code_snapshot' => 'X',
            'item_name_snapshot' => 'X',
            'qty' => 5,
            'unit' => 'PIECE',
            'conversion_factor_snapshot' => 1,
            'qty_pieces' => 5,
            'unit_price' => 1,
            'discount_amount' => 0,
            'discount_percent' => 0,
            'line_total' => 5,
            'returned_amount' => 0,
            'lost_amount' => 0,
            'net_line_total' => 5,
        ]);

        $allocation = GrossShipmentAllocation::query()->create([
            'sales_invoice_line_id' => $line->id,
            'conversion_factor_snapshot' => 1,
            'qty_shipped_pieces' => 5,
            'qty_delivered_pieces' => 0,
            'qty_returned_pieces' => 5,
            'qty_returned_received_pieces' => 5,
            'qty_with_courier_pieces' => 0,
            'qty_lost_pieces' => 0,
        ]);

        try {
            $this->stock->repairMoveOnHandToWithCourier(
                $this->item,
                5,
                GrossShipmentAllocation::class,
                (int) $allocation->id,
                $this->manager,
            );
            $this->fail('Expected insufficient stock');
        } catch (GrossException $e) {
            $this->assertSame('gross.errors.insufficient_stock', $e->getMessage());
        }

        $b = $this->balance();
        $this->assertSame(1, $b->qty_on_hand_pieces);
        $this->assertSame(0, $b->qty_with_courier_pieces);
        $this->assertSame(1, $this->totalTracked());
        $allocation->refresh();
        $this->assertSame(5, (int) $allocation->qty_returned_received_pieces);
        $this->assertSame(0, (int) $allocation->qty_with_courier_pieces);
    }
}
