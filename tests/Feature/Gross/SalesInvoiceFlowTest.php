<?php

namespace Tests\Feature\Gross;

use App\Exceptions\GrossException;
use App\Models\Gross\GrossItem;
use App\Models\Gross\GrossSalesInvoice;
use App\Models\Gross\GrossShippingCompany;
use App\Models\Gross\GrossStockBalance;
use App\Models\Gross\GrossStockMovement;
use App\Models\User;
use App\Models\ValuationAssignment;
use App\Services\Gross\AllocationService;
use App\Services\Gross\GrossUserPageService;
use App\Services\Gross\ItemService;
use App\Services\Gross\SalesInvoiceService;
use App\Services\Gross\ShippingCompanyService;
use App\Services\Gross\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ShipsGrossSalesToCourier;
use Tests\TestCase;

class SalesInvoiceFlowTest extends TestCase
{
    use RefreshDatabase;
    use ShipsGrossSalesToCourier;

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
            'name' => 'Dress',
            'pieces_per_carton' => 5,
            'reorder_level' => 2,
            'status' => GrossItem::STATUS_ACTIVE,
        ]);
        $this->stock->adjustIn($this->item, 20, GrossStockMovement::UNIT_PIECE, $this->manager);

        app(ShippingCompanyService::class)->create([
            'name' => 'Bosta',
            'status' => 'ACTIVE',
        ]);
    }

    public function test_confirm_starts_preparing_without_stock_then_ship_moves_stock_idempotently(): void
    {
        $shippingId = GrossShippingCompany::query()->value('id');
        $invoice = $this->invoices->createDraft($this->manager, [
            'customer_name' => 'Ali',
            'customer_phone' => '0100',
            'shipping_company_id' => $shippingId,
            'currency' => 'EGP',
            'shipping_fee' => 50,
        ], [[
            'item_id' => $this->item->id,
            'qty' => 10,
            'unit' => 'PIECE',
            'unit_price' => 100,
        ]]);

        $this->assertStringStartsWith('MH-', $invoice->invoice_number);

        $confirmed = $this->invoices->confirm($invoice, $this->manager);
        $this->assertSame(GrossSalesInvoice::STATUS_CONFIRMED, $confirmed->status);
        $this->assertSame(GrossSalesInvoice::FULFILLMENT_PREPARING, $confirmed->fulfillment_status);

        $balance = GrossStockBalance::query()->where('item_id', $this->item->id)->firstOrFail();
        $this->assertSame(20, $balance->qty_on_hand_pieces);
        $this->assertSame(0, $balance->qty_with_courier_pieces);
        $this->assertSame(0, GrossStockMovement::query()->where('type', GrossStockMovement::TYPE_SALE_OUT)->count());

        $shipped = $this->shipToCourier($confirmed, $this->manager);
        $this->assertSame(GrossSalesInvoice::FULFILLMENT_WITH_COURIER, $shipped->fulfillment_status);

        $balance->refresh();
        $this->assertSame(10, $balance->qty_on_hand_pieces);
        $this->assertSame(10, $balance->qty_with_courier_pieces);
        $this->assertSame(1, GrossStockMovement::query()->where('type', GrossStockMovement::TYPE_SALE_OUT)->count());

        $again = $this->invoices->confirm($shipped, $this->manager);
        $this->assertSame(GrossSalesInvoice::STATUS_CONFIRMED, $again->status);
        $this->invoices->handToCourier($shipped->fresh(), $this->manager);

        $balance->refresh();
        $this->assertSame(10, $balance->qty_on_hand_pieces);
        $this->assertSame(10, $balance->qty_with_courier_pieces);
        $this->assertSame(1, GrossStockMovement::query()->where('type', GrossStockMovement::TYPE_SALE_OUT)->count());
    }

    public function test_partial_return_and_loss_permissions(): void
    {
        $invoice = $this->confirmedInvoice(10);
        $alloc = $invoice->lines->first()->allocation;

        $this->allocations->partialReturn($alloc, 3, $this->manager);
        $alloc->refresh();
        $this->assertSame(3, $alloc->qty_returned_pieces);
        $this->assertSame(10, $alloc->qty_with_courier_pieces);
        $this->assertSame(0, $alloc->qty_returned_received_pieces);

        $balance = GrossStockBalance::query()->where('item_id', $this->item->id)->firstOrFail();
        $this->assertSame(10, $balance->qty_on_hand_pieces);
        $this->assertSame(10, $balance->qty_with_courier_pieces);

        $sales = User::factory()->sales()->create(['can_confirm_gross_loss' => false]);
        ValuationAssignment::query()->create([
            'user_id' => $sales->id,
            'assigned_by' => $this->manager->id,
            'module' => 'product',
        ]);
        app(GrossUserPageService::class)->applyRoleDefaults($sales);

        $this->assertFalse($sales->mayConfirmGrossLoss());
        $this->actingAs($sales->fresh())
            ->post(route('gross.sales.loss', $invoice), [
                'allocation_id' => $alloc->id,
                'qty_pieces' => 1,
            ])
            ->assertForbidden();

        $sales->update(['can_confirm_gross_loss' => true]);
        $this->assertTrue($sales->fresh()->mayConfirmGrossLoss());

        $this->actingAs($sales->fresh())
            ->post(route('gross.sales.loss', $invoice), [
                'allocation_id' => $alloc->id,
                'qty_pieces' => 1,
            ])
            ->assertRedirect();

        $alloc->refresh();
        $this->assertSame(1, $alloc->qty_lost_pieces);

        $this->allocations->confirmLoss($alloc->fresh(), 1, $this->manager);
        $alloc->refresh();
        $this->assertSame(2, $alloc->qty_lost_pieces);
        $this->assertSame(8, $alloc->qty_with_courier_pieces);

        $balance->refresh();
        $this->assertSame(10, $balance->qty_on_hand_pieces);
        $this->assertSame(8, $balance->qty_with_courier_pieces);
        $this->assertSame(2, $balance->qty_lost_pieces);
    }

    public function test_refuse_keeps_pieces_with_courier_and_marks_delivery_fee_due(): void
    {
        $invoice = $this->confirmedInvoice(8);
        $alloc = $invoice->lines->first()->allocation;
        $this->allocations->partialReturn($alloc, 3, $this->manager);

        $balanceBefore = GrossStockBalance::query()->where('item_id', $this->item->id)->firstOrFail();

        $refused = $this->invoices->refuse($invoice->fresh(), $this->manager, 'client refused');
        $this->assertSame(GrossSalesInvoice::STATUS_REFUSED, $refused->status);
        $this->assertSame(GrossSalesInvoice::COD_LOST, $refused->cod_collection_status);
        $this->assertSame(GrossSalesInvoice::SHIPPING_FEE_PENDING, $refused->shipping_fee_status);

        $alloc->refresh();
        $this->assertSame(8, $alloc->qty_returned_pieces);
        $this->assertSame(8, $alloc->qty_with_courier_pieces);
        $this->assertSame(8, $alloc->returnedPendingHandover());
        $this->assertSame(0, $alloc->qty_returned_received_pieces);

        $balance = GrossStockBalance::query()->where('item_id', $this->item->id)->firstOrFail();
        $this->assertSame((int) $balanceBefore->qty_on_hand_pieces, (int) $balance->qty_on_hand_pieces);
        $this->assertSame((int) $balanceBefore->qty_with_courier_pieces, (int) $balance->qty_with_courier_pieces);
        $this->assertSame(0, GrossStockMovement::query()->where('type', GrossStockMovement::TYPE_REFUSE_RETURN)->count());

        $this->invoices->receiveRefusedFromCourier($refused->fresh(), $this->manager);
        $alloc->refresh();
        $this->assertSame(0, $alloc->returnedPendingHandover());
        $this->assertSame(8, $alloc->qty_returned_received_pieces);
        $this->assertSame(0, $alloc->qty_with_courier_pieces);
        $this->assertSame(1, GrossStockMovement::query()->where('type', GrossStockMovement::TYPE_REFUSE_RETURN)->count());

        $again = $this->invoices->refuse($refused->fresh(), $this->manager);
        $this->assertSame(GrossSalesInvoice::STATUS_REFUSED, $again->status);
    }

    public function test_cancel_rules(): void
    {
        $draft = $this->invoices->createDraft($this->manager, [
            'customer_name' => 'X',
            'customer_phone' => '1',
            'currency' => 'EGP',
        ], [[
            'item_id' => $this->item->id,
            'qty' => 1,
            'unit' => 'PIECE',
            'unit_price' => 10,
        ]]);
        $cancelled = $this->invoices->cancel($draft);
        $this->assertSame(GrossSalesInvoice::STATUS_CANCELLED, $cancelled->status);

        $invoice = $this->confirmedInvoice(4);

        try {
            $this->invoices->cancel($invoice);
            $this->fail('Expected GrossException when cancelling with courier qty');
        } catch (GrossException $e) {
            $this->assertSame('gross.errors.cannot_cancel', $e->getMessage());
        }

        $alloc = $invoice->fresh(['lines.allocation'])->lines->first()->allocation;
        $this->allocations->partialReturn($alloc, 4, $this->manager);
        $this->allocations->receiveReturnedFromCourier($alloc->fresh(), $this->manager);
        $done = $this->invoices->cancel($invoice->fresh());
        $this->assertSame(GrossSalesInvoice::STATUS_CANCELLED, $done->status);
    }

    private function confirmedInvoice(int $pieces): GrossSalesInvoice
    {
        $shippingId = GrossShippingCompany::query()->value('id');
        $invoice = $this->invoices->createDraft($this->manager, [
            'customer_name' => 'Ali',
            'customer_phone' => '0100',
            'shipping_company_id' => $shippingId,
            'currency' => 'EGP',
            'shipping_fee' => 30,
        ], [[
            'item_id' => $this->item->id,
            'qty' => $pieces,
            'unit' => 'PIECE',
            'unit_price' => 50,
        ]]);

        return $this->shipToCourier($invoice, $this->manager)->load(['lines.allocation']);
    }
}
