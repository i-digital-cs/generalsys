<?php

namespace Tests\Feature\Gross;

use App\Models\Gross\GrossItem;
use App\Models\Gross\GrossStockMovement;
use App\Models\User;
use App\Models\ValuationAssignment;
use App\Services\Gross\ItemService;
use App\Services\Gross\SalesInvoiceService;
use App\Services\Gross\ShippingCompanyService;
use App\Services\Gross\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesPersonStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_sees_all_sales_people_and_sales_sees_own_only(): void
    {
        $manager = User::factory()->manager()->create();
        $salesA = User::factory()->sales()->create(['name_en' => 'Alice Sales', 'name_ar' => 'أليس']);
        $salesB = User::factory()->sales()->create(['name_en' => 'Bob Sales', 'name_ar' => 'بوب']);
        ValuationAssignment::query()->create([
            'user_id' => $salesA->id,
            'assigned_by' => $manager->id,
            'module' => 'product',
        ]);
        ValuationAssignment::query()->create([
            'user_id' => $salesB->id,
            'assigned_by' => $manager->id,
            'module' => 'product',
        ]);

        $company = app(ShippingCompanyService::class)->create(['name' => 'Courier', 'status' => 'ACTIVE']);
        $item = app(ItemService::class)->create(['name' => 'Shirt', 'status' => GrossItem::STATUS_ACTIVE]);
        app(StockService::class)->adjustIn($item, 20, GrossStockMovement::UNIT_PIECE, $manager);

        $invoices = app(SalesInvoiceService::class);
        $invoices->confirm($invoices->createDraft($manager, [
            'customer_name' => 'C1',
            'customer_phone' => '0111',
            'shipping_company_id' => $company->id,
            'sales_user_id' => $salesA->id,
            'currency' => 'EGP',
            'shipping_fee' => 10,
        ], [['item_id' => $item->id, 'qty' => 3, 'unit' => 'PIECE', 'unit_price' => 40]]), $manager);

        $invoices->confirm($invoices->createDraft($manager, [
            'customer_name' => 'C2',
            'customer_phone' => '0112',
            'shipping_company_id' => $company->id,
            'sales_user_id' => $salesB->id,
            'currency' => 'EGP',
            'shipping_fee' => 10,
        ], [['item_id' => $item->id, 'qty' => 1, 'unit' => 'PIECE', 'unit_price' => 100]]), $manager);

        $this->withSession(['locale' => 'en'])->actingAs($manager)
            ->get(route('gross.sales.stats'))
            ->assertOk()
            ->assertSee(__('gross.sales_stats.title'), false)
            ->assertSee('Alice Sales', false)
            ->assertSee('Bob Sales', false)
            ->assertSee('data-sales-stats-table', false);

        $this->actingAs($manager)
            ->get(route('gross.sales.stats.show', $salesA))
            ->assertOk()
            ->assertSee('Alice Sales', false);

        $this->withSession(['locale' => 'en'])->actingAs($salesA->fresh())
            ->get(route('gross.sales.stats'))
            ->assertOk()
            ->assertSee('Alice Sales', false)
            ->assertDontSee('Bob Sales', false);

        $this->actingAs($salesA->fresh())
            ->get(route('gross.sales.stats.show', $salesB))
            ->assertForbidden();

        $this->actingAs($salesA->fresh())
            ->get(route('gross.sales.stats.show', $salesA))
            ->assertOk();
    }

    public function test_product_dashboard_shows_mtd_sales_cards(): void
    {
        $manager = User::factory()->manager()->create();
        $sales = User::factory()->sales()->create(['name_en' => 'Top Seller', 'name_ar' => 'أعلى بائع']);
        $company = app(ShippingCompanyService::class)->create(['name' => 'Ship', 'status' => 'ACTIVE']);
        $item = app(ItemService::class)->create(['name' => 'Item', 'status' => GrossItem::STATUS_ACTIVE]);
        app(StockService::class)->adjustIn($item, 5, GrossStockMovement::UNIT_PIECE, $manager);

        $invoices = app(SalesInvoiceService::class);
        $invoices->confirm($invoices->createDraft($manager, [
            'customer_name' => 'Cust',
            'customer_phone' => '0120',
            'shipping_company_id' => $company->id,
            'sales_user_id' => $sales->id,
            'currency' => 'EGP',
            'shipping_fee' => 0,
        ], [['item_id' => $item->id, 'qty' => 2, 'unit' => 'PIECE', 'unit_price' => 75]]), $manager);

        $this->withSession(['locale' => 'en'])->actingAs($manager)
            ->get(route('portal.product'))
            ->assertOk()
            ->assertSee(__('gross.sales_stats.dashboard_title'), false)
            ->assertSee('Top Seller', false)
            ->assertSee('/product/gross/sales/stats', false);
    }

    public function test_currency_filter_excludes_other_currency(): void
    {
        $manager = User::factory()->manager()->create();
        $sales = User::factory()->sales()->create(['name_en' => 'AED Seller', 'name_ar' => 'بائع درهم']);
        $item = app(ItemService::class)->create(['name' => 'AED Item', 'status' => GrossItem::STATUS_ACTIVE]);
        app(StockService::class)->adjustIn($item, 5, GrossStockMovement::UNIT_PIECE, $manager);

        $invoices = app(SalesInvoiceService::class);
        $invoices->confirm($invoices->createDraft($manager, [
            'customer_name' => 'Dubai',
            'customer_phone' => '0500',
            'sales_user_id' => $sales->id,
            'currency' => 'AED',
            'shipping_fee' => 0,
        ], [['item_id' => $item->id, 'qty' => 1, 'unit' => 'PIECE', 'unit_price' => 90]]), $manager);

        $this->withSession(['locale' => 'en'])->actingAs($manager)
            ->get(route('gross.sales.stats', ['currency' => 'EGP']))
            ->assertOk()
            ->assertSee(__('gross.sales_stats.empty'), false);

        $aed = $this->withSession(['locale' => 'en'])->actingAs($manager)
            ->get(route('gross.sales.stats', ['currency' => 'AED']))
            ->assertOk();
        preg_match('/<tbody>(.*?)<\/tbody>/s', $aed->getContent(), $body);
        $this->assertNotEmpty($body[1] ?? null);
        $this->assertStringContainsString('AED Seller', $body[1]);
    }

    public function test_sales_person_filter_narrows_rows(): void
    {
        $manager = User::factory()->manager()->create();
        $salesA = User::factory()->sales()->create(['name_en' => 'Filter Alice', 'name_ar' => 'أليس']);
        $salesB = User::factory()->sales()->create(['name_en' => 'Filter Bob', 'name_ar' => 'بوب']);
        $item = app(ItemService::class)->create(['name' => 'Filter Item', 'status' => GrossItem::STATUS_ACTIVE]);
        app(StockService::class)->adjustIn($item, 10, GrossStockMovement::UNIT_PIECE, $manager);

        $invoices = app(SalesInvoiceService::class);
        $invoices->confirm($invoices->createDraft($manager, [
            'customer_name' => 'C1',
            'customer_phone' => '0701',
            'sales_user_id' => $salesA->id,
            'currency' => 'EGP',
            'shipping_fee' => 0,
        ], [['item_id' => $item->id, 'qty' => 1, 'unit' => 'PIECE', 'unit_price' => 50]]), $manager);
        $invoices->confirm($invoices->createDraft($manager, [
            'customer_name' => 'C2',
            'customer_phone' => '0702',
            'sales_user_id' => $salesB->id,
            'currency' => 'EGP',
            'shipping_fee' => 0,
        ], [['item_id' => $item->id, 'qty' => 1, 'unit' => 'PIECE', 'unit_price' => 60]]), $manager);

        $response = $this->withSession(['locale' => 'en'])->actingAs($manager)
            ->get(route('gross.sales.stats', ['sales_user_id' => $salesA->id]))
            ->assertOk()
            ->assertSee('name="sales_user_id"', false);

        preg_match('/<tbody>(.*?)<\/tbody>/s', $response->getContent(), $body);
        $this->assertNotEmpty($body[1] ?? null);
        $this->assertStringContainsString('Filter Alice', $body[1]);
        $this->assertStringNotContainsString('Filter Bob', $body[1]);
        $this->assertStringContainsString('50.00', $body[1]);
    }
}
