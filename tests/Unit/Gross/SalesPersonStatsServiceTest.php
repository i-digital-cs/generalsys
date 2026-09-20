<?php

namespace Tests\Unit\Gross;

use App\Models\Gross\GrossItem;
use App\Models\Gross\GrossStockMovement;
use App\Models\User;
use App\Services\Gross\ItemService;
use App\Services\Gross\SalesInvoiceService;
use App\Services\Gross\SalesPersonStatsService;
use App\Services\Gross\ShippingCompanyService;
use App\Services\Gross\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ShipsGrossSalesToCourier;
use Tests\TestCase;

class SalesPersonStatsServiceTest extends TestCase
{
    use RefreshDatabase;
    use ShipsGrossSalesToCourier;

    public function test_resolve_period_defaults_to_current_month(): void
    {
        $period = app(SalesPersonStatsService::class)->resolvePeriod([]);

        $this->assertSame(now()->format('Y-m'), $period['month']);
        $this->assertSame(now()->startOfMonth()->toDateString(), $period['filters']['from']);
        $this->assertSame(now()->endOfMonth()->toDateString(), $period['filters']['to']);
        $this->assertSame('EGP', $period['currency']);
    }

    public function test_net_merchandise_excludes_shipping_and_groups_by_sales_user(): void
    {
        $manager = User::factory()->manager()->create();
        $salesA = User::factory()->sales()->create(['name_en' => 'Seller A', 'name_ar' => 'بائع أ']);
        $salesB = User::factory()->sales()->create(['name_en' => 'Seller B', 'name_ar' => 'بائع ب']);
        $company = app(ShippingCompanyService::class)->create(['name' => 'Bosta', 'status' => 'ACTIVE']);
        $item = app(ItemService::class)->create(['name' => 'Dress', 'status' => GrossItem::STATUS_ACTIVE]);
        app(StockService::class)->adjustIn($item, 20, GrossStockMovement::UNIT_PIECE, $manager);

        $invoices = app(SalesInvoiceService::class);
        $invoices->confirm($invoices->createDraft($manager, [
            'customer_name' => 'C1',
            'customer_phone' => '0101',
            'shipping_company_id' => $company->id,
            'sales_user_id' => $salesA->id,
            'currency' => 'EGP',
            'shipping_fee' => 50,
        ], [['item_id' => $item->id, 'qty' => 2, 'unit' => 'PIECE', 'unit_price' => 100]]), $manager);

        $invoices->confirm($invoices->createDraft($manager, [
            'customer_name' => 'C2',
            'customer_phone' => '0102',
            'shipping_company_id' => $company->id,
            'sales_user_id' => $salesB->id,
            'currency' => 'EGP',
            'shipping_fee' => 30,
        ], [['item_id' => $item->id, 'qty' => 1, 'unit' => 'PIECE', 'unit_price' => 80]]), $manager);

        $summary = app(SalesPersonStatsService::class)->summarize($manager, [
            'month' => now()->format('Y-m'),
            'currency' => 'EGP',
        ]);

        $this->assertCount(2, $summary['rows']);
        $byId = collect($summary['rows'])->keyBy('sales_user_id');
        $this->assertSame('200.00', $byId[$salesA->id]['net_merchandise']);
        $this->assertSame('80.00', $byId[$salesB->id]['net_merchandise']);
        $this->assertSame('280.00', $summary['totals']['net_merchandise']);
        $this->assertSame(2, $summary['totals']['invoices_count']);
    }

    public function test_refuse_zeros_net_merchandise_for_salesperson(): void
    {
        $manager = User::factory()->manager()->create();
        $sales = User::factory()->sales()->create();
        $company = app(ShippingCompanyService::class)->create(['name' => 'X', 'status' => 'ACTIVE']);
        $item = app(ItemService::class)->create(['name' => 'Coat', 'status' => GrossItem::STATUS_ACTIVE]);
        app(StockService::class)->adjustIn($item, 5, GrossStockMovement::UNIT_PIECE, $manager);

        $service = app(SalesInvoiceService::class);
        $confirmed = $this->shipToCourier($service->createDraft($manager, [
            'customer_name' => 'R',
            'customer_phone' => '0103',
            'shipping_company_id' => $company->id,
            'sales_user_id' => $sales->id,
            'currency' => 'EGP',
            'shipping_fee' => 20,
        ], [['item_id' => $item->id, 'qty' => 2, 'unit' => 'PIECE', 'unit_price' => 50]]), $manager);

        $service->refuse($confirmed, $manager, 'no');

        $summary = app(SalesPersonStatsService::class)->summarize($manager, [
            'month' => now()->format('Y-m'),
            'currency' => 'EGP',
        ]);

        $row = collect($summary['rows'])->firstWhere('sales_user_id', $sales->id);
        $this->assertNotNull($row);
        $this->assertSame(1, $row['refused_count']);
        $this->assertSame('0.00', $row['net_merchandise']);
    }
}
