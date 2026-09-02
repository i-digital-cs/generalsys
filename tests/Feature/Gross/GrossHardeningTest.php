<?php

namespace Tests\Feature\Gross;

use App\Exceptions\GrossException;
use App\Models\Gross\GrossItem;
use App\Models\Gross\GrossSalesInvoice;
use App\Models\Gross\GrossShippingCompany;
use App\Models\Gross\GrossStockBalance;
use App\Models\Gross\GrossStockMovement;
use App\Models\User;
use App\Services\Gross\AllocationService;
use App\Services\Gross\ItemService;
use App\Services\Gross\SalesInvoiceService;
use App\Services\Gross\ShippingCompanyService;
use App\Services\Gross\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T-10.9 — Hardening, regression & edge cases.
 */
class GrossHardeningTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private GrossItem $item;

    private SalesInvoiceService $invoices;

    private AllocationService $allocations;

    private StockService $stock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->manager()->create();
        $this->invoices = app(SalesInvoiceService::class);
        $this->allocations = app(AllocationService::class);
        $this->stock = app(StockService::class);

        $this->item = app(ItemService::class)->create([
            'name' => 'Hardening SKU',
            'pieces_per_carton' => 12,
            'reorder_level' => 5,
            'status' => GrossItem::STATUS_ACTIVE,
        ]);
        $this->stock->adjustIn($this->item, 100, GrossStockMovement::UNIT_PIECE, $this->manager);

        app(ShippingCompanyService::class)->create([
            'name' => 'Aramex',
            'status' => 'ACTIVE',
        ]);
    }

    public function test_second_sale_cannot_overdraw_stock(): void
    {
        $first = $this->confirmedInvoice(80);
        $this->assertSame(GrossSalesInvoice::STATUS_CONFIRMED, $first->status);

        $draft = $this->draftInvoice(30);

        try {
            $this->invoices->confirm($draft, $this->manager);
            $this->fail('Expected insufficient stock');
        } catch (GrossException $e) {
            $this->assertSame('gross.errors.insufficient_stock', $e->getMessage());
        }

        $balance = GrossStockBalance::query()->where('item_id', $this->item->id)->firstOrFail();
        $this->assertSame(20, $balance->qty_on_hand_pieces);
        $this->assertSame(80, $balance->qty_with_courier_pieces);
        $this->assertSame(1, GrossStockMovement::query()->where('type', GrossStockMovement::TYPE_SALE_OUT)->count());
    }

    public function test_invalid_return_and_loss_qty_rejected(): void
    {
        $invoice = $this->confirmedInvoice(5);
        $alloc = $invoice->lines->first()->allocation;

        try {
            $this->allocations->partialReturn($alloc, 6, $this->manager);
            $this->fail('Expected allocation qty error');
        } catch (GrossException $e) {
            $this->assertSame('gross.errors.allocation_qty', $e->getMessage());
        }

        try {
            $this->allocations->confirmLoss($alloc->fresh(), 6, $this->manager);
            $this->fail('Expected allocation qty error');
        } catch (GrossException $e) {
            $this->assertSame('gross.errors.allocation_qty', $e->getMessage());
        }

        $alloc->refresh();
        $this->assertSame(5, $alloc->qty_with_courier_pieces);
    }

    public function test_refuse_keeps_lost_pieces_and_pieces_with_courier(): void
    {
        $invoice = $this->confirmedInvoice(10);
        $alloc = $invoice->lines->first()->allocation;

        $this->allocations->partialReturn($alloc, 2, $this->manager);
        $this->allocations->confirmLoss($alloc->fresh(), 1, $this->manager);

        $balanceBefore = GrossStockBalance::query()->where('item_id', $this->item->id)->firstOrFail();

        $refused = $this->invoices->refuse($invoice->fresh(), $this->manager, 'refused');
        $this->assertSame(GrossSalesInvoice::STATUS_REFUSED, $refused->status);
        $this->assertSame(GrossSalesInvoice::SHIPPING_FEE_PENDING, $refused->shipping_fee_status);

        $alloc->refresh();
        $this->assertSame(10, $alloc->qty_shipped_pieces);
        $this->assertSame(9, $alloc->qty_returned_pieces);
        $this->assertSame(1, $alloc->qty_lost_pieces);
        $this->assertSame(9, $alloc->qty_with_courier_pieces);
        $this->assertSame(9, $alloc->returnedPendingHandover());

        $balance = GrossStockBalance::query()->where('item_id', $this->item->id)->firstOrFail();
        $this->assertSame((int) $balanceBefore->qty_on_hand_pieces, (int) $balance->qty_on_hand_pieces);
        $this->assertSame((int) $balanceBefore->qty_with_courier_pieces, (int) $balance->qty_with_courier_pieces);
        $this->assertSame(1, $balance->qty_lost_pieces);
    }

    public function test_line_snapshot_keeps_item_code_after_rename(): void
    {
        $invoice = $this->confirmedInvoice(2);
        $line = $invoice->lines->first();
        $code = $this->item->code;

        $this->assertSame('PIECE', $line->unit);
        $this->assertSame(1, $line->conversion_factor_snapshot);
        $this->assertSame(2, $line->qty_pieces);
        $this->assertSame($code, $line->item_code_snapshot);

        app(ItemService::class)->update($this->item, [
            'name' => 'Renamed SKU',
            'reorder_level' => $this->item->reorder_level,
            'status' => $this->item->status,
        ]);

        $line->refresh();
        $this->assertSame($code, $line->item_code_snapshot);
        $this->assertSame(2, $line->qty_pieces);
        $this->assertSame('Hardening SKU', $line->item_name_snapshot);
    }

    public function test_currency_immutable_after_confirm(): void
    {
        $invoice = $this->confirmedInvoice(3);

        try {
            $this->invoices->updateDraft($invoice, [
                'customer_name' => $invoice->customer_name,
                'customer_phone' => $invoice->customer_phone,
                'currency' => 'AED',
            ], [[
                'item_id' => $this->item->id,
                'qty' => 3,
                'unit' => 'PIECE',
                'unit_price' => 10,
            ]]);
            $this->fail('Expected not draft');
        } catch (GrossException $e) {
            $this->assertSame('gross.errors.not_draft', $e->getMessage());
        }

        $this->assertSame('EGP', $invoice->fresh()->currency);
    }

    public function test_duplicate_return_does_not_over_return(): void
    {
        $invoice = $this->confirmedInvoice(4);
        $alloc = $invoice->lines->first()->allocation;
        $this->allocations->partialReturn($alloc, 4, $this->manager);

        try {
            $this->allocations->partialReturn($alloc->fresh(), 1, $this->manager);
            $this->fail('Expected allocation qty error');
        } catch (GrossException $e) {
            $this->assertSame('gross.errors.allocation_qty', $e->getMessage());
        }

        $alloc->refresh();
        $this->assertSame(4, $alloc->qty_returned_pieces);
        $this->assertSame(4, $alloc->qty_with_courier_pieces);
    }

    public function test_ledger_matches_balance_after_flow(): void
    {
        $invoice = $this->confirmedInvoice(10);
        $alloc = $invoice->lines->first()->allocation;
        $this->allocations->partialReturn($alloc, 3, $this->manager);
        $this->allocations->confirmLoss($alloc->fresh(), 2, $this->manager);
        $this->invoices->refuse($invoice->fresh(), $this->manager);

        $balance = GrossStockBalance::query()->where('item_id', $this->item->id)->firstOrFail();
        $saleOut = (int) GrossStockMovement::query()
            ->where('item_id', $this->item->id)
            ->where('type', GrossStockMovement::TYPE_SALE_OUT)
            ->sum('qty_pieces');
        $returns = (int) GrossStockMovement::query()
            ->where('item_id', $this->item->id)
            ->whereIn('type', [GrossStockMovement::TYPE_SALE_RETURN, GrossStockMovement::TYPE_REFUSE_RETURN])
            ->sum('qty_pieces');
        $lost = (int) GrossStockMovement::query()
            ->where('item_id', $this->item->id)
            ->where('type', GrossStockMovement::TYPE_LOSS_CONFIRM)
            ->sum('qty_pieces');
        $adjustedIn = (int) GrossStockMovement::query()
            ->where('item_id', $this->item->id)
            ->where('type', GrossStockMovement::TYPE_ADJUSTMENT_IN)
            ->sum('qty_pieces');

        $this->assertSame($adjustedIn - $saleOut + $returns, $balance->qty_on_hand_pieces);
        $this->assertSame($lost, $balance->qty_lost_pieces);
        $this->assertSame(8, $balance->qty_with_courier_pieces);
    }

    private function draftInvoice(int $pieces): GrossSalesInvoice
    {
        $shippingId = GrossShippingCompany::query()->value('id');

        return $this->invoices->createDraft($this->manager, [
            'customer_name' => 'Cust',
            'customer_phone' => '010',
            'shipping_company_id' => $shippingId,
            'currency' => 'EGP',
            'shipping_fee' => 25,
        ], [[
            'item_id' => $this->item->id,
            'qty' => $pieces,
            'unit' => 'PIECE',
            'unit_price' => 40,
        ]]);
    }

    private function confirmedInvoice(int $pieces): GrossSalesInvoice
    {
        return $this->invoices->confirm($this->draftInvoice($pieces), $this->manager)
            ->load(['lines.allocation']);
    }
}
