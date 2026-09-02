<?php

namespace Tests\Unit\Gross;

use App\Models\Gross\GrossItem;
use App\Models\Gross\GrossStockMovement;
use App\Models\User;
use App\Services\Gross\AllocationService;
use App\Services\Gross\ItemService;
use App\Services\Gross\SalesInvoiceService;
use App\Services\Gross\ShippingCompanyService;
use App\Services\Gross\ShippingReturnsReportService;
use App\Services\Gross\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShippingReturnsReportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_totals_count_events_and_pieces(): void
    {
        $manager = User::factory()->manager()->create();
        $company = app(ShippingCompanyService::class)->create(['name' => 'Courier', 'status' => 'ACTIVE']);
        $item = app(ItemService::class)->create([
            'name' => 'Shirt',
            'reorder_level' => 1,
            'status' => GrossItem::STATUS_ACTIVE,
        ]);
        app(StockService::class)->adjustIn($item, 20, GrossStockMovement::UNIT_PIECE, $manager);

        $invoices = app(SalesInvoiceService::class);
        $allocations = app(AllocationService::class);
        $report = app(ShippingReturnsReportService::class);

        $invoice = $invoices->createDraft($manager, [
            'customer_name' => 'A',
            'customer_phone' => '1',
            'shipping_company_id' => $company->id,
            'currency' => 'EGP',
        ], [[
            'item_id' => $item->id,
            'qty' => 8,
            'unit' => 'PIECE',
            'unit_price' => 10,
        ]]);
        $confirmed = $invoices->confirm($invoice, $manager)->load(['lines.allocation']);
        $allocations->partialReturn($confirmed->lines->first()->allocation, 2, $manager);

        $refuseDraft = $invoices->createDraft($manager, [
            'customer_name' => 'B',
            'customer_phone' => '2',
            'shipping_company_id' => $company->id,
            'currency' => 'EGP',
        ], [[
            'item_id' => $item->id,
            'qty' => 3,
            'unit' => 'PIECE',
            'unit_price' => 10,
        ]]);
        $invoices->refuse($invoices->confirm($refuseDraft, $manager), $manager);

        $totals = $report->totals($company);

        $this->assertSame(2, $totals['event_count']);
        $this->assertSame(5, $totals['total_pieces']);
    }

    public function test_return_kind_mapping(): void
    {
        $report = app(ShippingReturnsReportService::class);

        $partial = new GrossStockMovement(['type' => GrossStockMovement::TYPE_SALE_RETURN_CLIENT]);
        $refuse = new GrossStockMovement(['type' => GrossStockMovement::TYPE_REFUSE_RETURN]);
        $handover = new GrossStockMovement(['type' => GrossStockMovement::TYPE_SALE_RETURN]);

        $this->assertSame('PARTIAL', $report->returnKind($partial));
        $this->assertSame('REFUSE', $report->returnKind($refuse));
        $this->assertSame('WITH_COURIER', $report->locationAfterReturn($partial));
        $this->assertSame('WAREHOUSE', $report->locationAfterReturn($refuse));
        $this->assertSame('WAREHOUSE', $report->locationAfterReturn($handover));
    }
}
