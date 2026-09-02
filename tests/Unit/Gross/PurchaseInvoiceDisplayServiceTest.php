<?php

namespace Tests\Unit\Gross;

use App\Models\Gross\GrossItem;
use App\Models\Gross\GrossSupplier;
use App\Models\User;
use App\Services\Gross\ItemService;
use App\Services\Gross\PurchaseInvoiceDisplayService;
use App\Services\Gross\PurchaseInvoiceService;
use App\Services\Gross\PurchaseReturnService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseInvoiceDisplayServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_net_totals_after_partial_return(): void
    {
        $manager = User::factory()->manager()->create();
        $supplier = GrossSupplier::query()->create(['name' => 'Mill', 'phone' => '0100']);
        $item = app(ItemService::class)->create(['name' => 'Dress', 'status' => GrossItem::STATUS_ACTIVE]);

        $purchases = app(PurchaseInvoiceService::class);
        $invoice = $purchases->confirm($purchases->createDraft($manager, [
            'supplier_id' => $supplier->id,
            'supplier_name' => $supplier->name,
            'supplier_phone' => $supplier->phone,
            'currency' => 'EGP',
        ], [[
            'item_id' => $item->id,
            'qty' => 50,
            'unit' => 'PIECE',
            'buy_price' => 300,
            'sale_price' => 350,
        ]]), $manager)->load('lines');

        app(PurchaseReturnService::class)->create($manager, $invoice, [[
            'purchase_invoice_line_id' => $invoice->lines->first()->id,
            'qty' => 22,
        ]]);

        $display = app(PurchaseInvoiceDisplayService::class)->build($invoice->fresh(['lines']));

        $this->assertTrue($display['has_returns']);
        $this->assertSame('6600.00', number_format($display['returned_merchandise'], 2, '.', ''));
        $this->assertSame('8400.00', number_format($display['net_grand_total'], 2, '.', ''));
        $this->assertSame('15000.00', number_format($display['original_total'], 2, '.', ''));
    }
}
