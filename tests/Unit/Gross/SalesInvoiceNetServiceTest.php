<?php

namespace Tests\Unit\Gross;

use App\Models\Gross\GrossItem;
use App\Models\Gross\GrossSalesInvoice;
use App\Models\Gross\GrossStockMovement;
use App\Models\User;
use App\Services\Gross\AllocationService;
use App\Services\Gross\ItemService;
use App\Services\Gross\SalesInvoiceNetService;
use App\Services\Gross\SalesInvoiceService;
use App\Services\Gross\ShippingCompanyService;
use App\Services\Gross\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ShipsGrossSalesToCourier;
use Tests\TestCase;

class SalesInvoiceNetServiceTest extends TestCase
{
    use RefreshDatabase;
    use ShipsGrossSalesToCourier;

    public function test_partial_return_persists_net_columns_on_invoice_and_line(): void
    {
        $manager = User::factory()->manager()->create();
        $item = app(ItemService::class)->create([
            'name' => 'Dress',
            'reorder_level' => 1,
            'status' => GrossItem::STATUS_ACTIVE,
        ]);
        app(StockService::class)->adjustIn($item, 20, GrossStockMovement::UNIT_PIECE, $manager);

        $company = app(ShippingCompanyService::class)->create(['name' => 'Courier', 'status' => 'ACTIVE']);
        $invoices = app(SalesInvoiceService::class);
        $confirmed = $this->shipToCourier($invoices->createDraft($manager, [
            'customer_name' => 'Ali',
            'customer_phone' => '0100',
            'shipping_company_id' => $company->id,
            'currency' => 'EGP',
            'shipping_fee' => 50,
        ], [[
            'item_id' => $item->id,
            'qty' => 10,
            'unit' => 'PIECE',
            'unit_price' => 100,
        ]]), $manager)->load(['lines.allocation']);

        $this->assertDatabaseHas('gross_sales_invoices', [
            'id' => $confirmed->id,
            'total' => '1050.00',
            'net_total' => '1050.00',
            'returned_merchandise' => '0.00',
        ]);

        app(AllocationService::class)->partialReturn($confirmed->lines->first()->allocation, 3, $manager);

        $this->assertDatabaseHas('gross_sales_invoice_lines', [
            'id' => $confirmed->lines->first()->id,
            'returned_amount' => '300.00',
            'net_line_total' => '700.00',
        ]);

        $this->assertDatabaseHas('gross_sales_invoices', [
            'id' => $confirmed->id,
            'total' => '1050.00',
            'returned_merchandise' => '300.00',
            'net_subtotal' => '700.00',
            'net_total' => '750.00',
        ]);
    }

    public function test_loss_reduces_net_line_total_in_database(): void
    {
        $manager = User::factory()->manager()->create();
        $item = app(ItemService::class)->create([
            'name' => 'Shirt',
            'status' => GrossItem::STATUS_ACTIVE,
        ]);
        app(StockService::class)->adjustIn($item, 10, GrossStockMovement::UNIT_PIECE, $manager);

        $invoices = app(SalesInvoiceService::class);
        $confirmed = $this->shipToCourier($invoices->createDraft($manager, [
            'customer_name' => 'X',
            'customer_phone' => '1',
            'currency' => 'EGP',
            'shipping_fee' => 0,
        ], [[
            'item_id' => $item->id,
            'qty' => 10,
            'unit' => 'PIECE',
            'unit_price' => 50,
        ]]), $manager)->load(['lines.allocation']);

        app(AllocationService::class)->confirmLoss($confirmed->lines->first()->allocation, 2, $manager);

        $this->assertDatabaseHas('gross_sales_invoice_lines', [
            'id' => $confirmed->lines->first()->id,
            'lost_amount' => '100.00',
            'net_line_total' => '400.00',
        ]);
    }

    public function test_refuse_zeros_net_total_in_database(): void
    {
        $manager = User::factory()->manager()->create();
        $item = app(ItemService::class)->create(['name' => 'Coat', 'status' => GrossItem::STATUS_ACTIVE]);
        app(StockService::class)->adjustIn($item, 10, GrossStockMovement::UNIT_PIECE, $manager);

        $invoices = app(SalesInvoiceService::class);
        $confirmed = $this->shipToCourier($invoices->createDraft($manager, [
            'customer_name' => 'Y',
            'customer_phone' => '2',
            'currency' => 'EGP',
            'shipping_fee' => 20,
        ], [[
            'item_id' => $item->id,
            'qty' => 5,
            'unit' => 'PIECE',
            'unit_price' => 40,
        ]]), $manager);

        $refused = $invoices->refuse($confirmed, $manager);

        $this->assertSame(GrossSalesInvoice::STATUS_REFUSED, $refused->status);
        $this->assertDatabaseHas('gross_sales_invoices', [
            'id' => $refused->id,
            'net_total' => '0.00',
            'returned_merchandise' => '200.00',
        ]);
    }

    public function test_recalculate_is_idempotent(): void
    {
        $manager = User::factory()->manager()->create();
        $item = app(ItemService::class)->create(['name' => 'Hat', 'status' => GrossItem::STATUS_ACTIVE]);
        app(StockService::class)->adjustIn($item, 5, GrossStockMovement::UNIT_PIECE, $manager);

        $invoice = app(SalesInvoiceService::class)->confirm(
            app(SalesInvoiceService::class)->createDraft($manager, [
                'customer_name' => 'Z',
                'customer_phone' => '3',
                'currency' => 'EGP',
            ], [[
                'item_id' => $item->id,
                'qty' => 4,
                'unit' => 'PIECE',
                'unit_price' => 25,
            ]]),
            $manager,
        );

        $net = app(SalesInvoiceNetService::class);
        $first = $net->recalculateInvoice($invoice->fresh(['lines.allocation']));
        $second = $net->recalculateInvoice($first->fresh(['lines.allocation']));

        $this->assertSame($first->net_total, $second->net_total);
        $this->assertSame($first->lines->first()->net_line_total, $second->lines->first()->net_line_total);
    }
}
