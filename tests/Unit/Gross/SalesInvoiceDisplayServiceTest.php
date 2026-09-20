<?php

namespace Tests\Unit\Gross;

use App\Models\Gross\GrossItem;
use App\Models\Gross\GrossShippingCompany;
use App\Models\Gross\GrossSupplier;
use App\Models\User;
use App\Services\Gross\AllocationService;
use App\Services\Gross\ItemService;
use App\Services\Gross\PurchaseInvoiceService;
use App\Services\Gross\PurchaseReturnService;
use App\Services\Gross\SalesInvoiceDisplayService;
use App\Services\Gross\SalesInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ShipsGrossSalesToCourier;
use Tests\TestCase;

class SalesInvoiceDisplayServiceTest extends TestCase
{
    use RefreshDatabase;
    use ShipsGrossSalesToCourier;

    public function test_piece_summary_and_stock_match_user_scenario(): void
    {
        $manager = User::factory()->manager()->create();
        $supplier = GrossSupplier::query()->create([
            'name' => 'Mill',
            'phone' => '0100',
        ]);
        $item = app(ItemService::class)->create([
            'name' => 'Dress',
            'status' => GrossItem::STATUS_ACTIVE,
        ]);

        $purchases = app(PurchaseInvoiceService::class);
        $purchase = $purchases->createDraft($manager, [
            'supplier_id' => $supplier->id,
            'supplier_name' => $supplier->name,
            'supplier_phone' => $supplier->phone,
            'currency' => 'EGP',
        ], [[
            'item_id' => $item->id,
            'qty' => 50,
            'unit' => 'PIECE',
            'buy_price' => 10,
            'sale_price' => 20,
        ]]);
        $purchases->confirm($purchase, $manager);

        app(PurchaseReturnService::class)->create($manager, $purchase, [[
            'purchase_invoice_line_id' => $purchase->lines->first()->id,
            'qty' => 22,
            'unit' => 'PIECE',
        ]]);

        $shipping = GrossShippingCompany::query()->create(['name' => 'Bosta', 'status' => 'ACTIVE']);
        $invoice = app(SalesInvoiceService::class)->createDraft($manager, [
            'customer_name' => 'Sara',
            'customer_phone' => '0101',
            'shipping_company_id' => $shipping->id,
            'currency' => 'EGP',
            'shipping_fee' => 0,
        ], [[
            'item_id' => $item->id,
            'qty' => 10,
            'unit' => 'PIECE',
            'unit_price' => 100,
        ]]);

        $confirmed = $this->shipToCourier($invoice, $manager)->load(['lines.allocation', 'lines.item.balance']);
        app(AllocationService::class)->partialReturn($confirmed->lines->first()->allocation, 2, $manager);
        $confirmed->refresh()->load(['lines.allocation', 'lines.item.balance']);

        $display = app(SalesInvoiceDisplayService::class)->build($confirmed);

        $this->assertSame(10, $display['piece_summary']['total_on_invoice']);
        $this->assertSame(2, $display['piece_summary']['total_returned']);
        $this->assertSame(2, $display['piece_summary']['total_returned_with_courier']);
        $this->assertSame(8, $display['piece_summary']['total_awaiting_delivery']);
        $this->assertSame(10, $display['piece_summary']['total_with_courier']);
        $this->assertSame(0, $display['piece_summary']['total_delivered']);

        $stock = $display['lines'][0]['stock'];
        $this->assertSame(18, $stock['on_hand']);
        $this->assertSame(10, $stock['with_courier']);
        $this->assertSame(28, $stock['total_tracked']);
        $this->assertSame(10, $stock['on_invoice_with_courier']);
        $this->assertSame(2, $stock['on_invoice_returned']);
    }
}
