<?php

namespace Tests\Unit\Gross;

use App\Models\Gross\GrossItem;
use App\Models\Gross\GrossStockMovement;
use App\Models\Gross\GrossTreasuryAccount;
use App\Models\User;
use App\Services\Gross\AllocationService;
use App\Services\Gross\ItemService;
use App\Services\Gross\SalesInvoiceService;
use App\Services\Gross\ShippingCollectionStatementService;
use App\Services\Gross\ShippingCompanyService;
use App\Services\Gross\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShippingCollectionStatementServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_running_balance_invoices_minus_returns_and_refuse(): void
    {
        $manager = User::factory()->manager()->create();
        $company = app(ShippingCompanyService::class)->create(['name' => 'Bosta', 'status' => 'ACTIVE']);
        $item = app(ItemService::class)->create([
            'name' => 'Dress',
            'status' => GrossItem::STATUS_ACTIVE,
        ]);
        app(StockService::class)->adjustIn($item, 30, GrossStockMovement::UNIT_PIECE, $manager);

        $invoices = app(SalesInvoiceService::class);
        $allocations = app(AllocationService::class);
        $statement = app(ShippingCollectionStatementService::class);

        $partial = $invoices->confirm($invoices->createDraft($manager, [
            'customer_name' => 'Partial',
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

        $allocations->partialReturn($partial->lines->first()->allocation, 3, $manager);

        $refuseDraft = $invoices->createDraft($manager, [
            'customer_name' => 'Refused',
            'customer_phone' => '0101',
            'shipping_company_id' => $company->id,
            'currency' => 'EGP',
            'shipping_fee' => 20,
        ], [[
            'item_id' => $item->id,
            'qty' => 5,
            'unit' => 'PIECE',
            'unit_price' => 40,
        ]]);
        $invoices->refuse($invoices->confirm($refuseDraft, $manager), $manager);

        $built = $statement->build($company, ['currency' => 'EGP']);

        $this->assertSame('900.00', $built['total_invoices']);
        $this->assertSame('200.00', $built['total_adjustments']);
        $this->assertSame('0.00', $built['total_collections']);
        $this->assertSame('700.00', $built['balance']);
        $this->assertSame('20.00', $built['total_delivery_fees']);
        $this->assertCount(4, $built['entries']);
        $this->assertSame('700.00', $built['entries'][0]['debit']);
        $this->assertSame('REFUSE', $built['entries'][2]['type']);
        $this->assertSame('700.00', $built['entries'][2]['balance']);
        $this->assertTrue($built['entries'][3]['informational']);
        $this->assertSame('700.00', $built['entries'][3]['balance']);
    }

    public function test_collection_credit_for_collected_shipping_fee(): void
    {
        $manager = User::factory()->manager()->create();
        $company = app(ShippingCompanyService::class)->create(['name' => 'Courier', 'status' => 'ACTIVE']);
        $item = app(ItemService::class)->create(['name' => 'Hat', 'status' => GrossItem::STATUS_ACTIVE]);
        app(StockService::class)->adjustIn($item, 5, GrossStockMovement::UNIT_PIECE, $manager);

        $invoices = app(SalesInvoiceService::class);
        $confirmed = $invoices->confirm($invoices->createDraft($manager, [
            'customer_name' => 'Ali',
            'customer_phone' => '0100',
            'shipping_company_id' => $company->id,
            'currency' => 'EGP',
            'shipping_fee' => 25,
        ], [[
            'item_id' => $item->id,
            'qty' => 1,
            'unit' => 'PIECE',
            'unit_price' => 100,
        ]]), $manager);

        $invoices->collectCod($confirmed, $manager);

        $built = app(ShippingCollectionStatementService::class)->build($company, ['currency' => 'EGP']);

        $this->assertSame('100.00', $built['total_invoices']);
        $this->assertSame('100.00', $built['total_collections']);
        $this->assertSame('0.00', $built['balance']);
        $this->assertSame('COLLECTION', $built['entries'][1]['type']);
        $this->assertSame('100.00', $built['entries'][1]['credit']);
    }

    public function test_partial_return_and_treasury_collection_zeros_balance_excluding_shipping(): void
    {
        $manager = User::factory()->manager()->create();
        $company = app(ShippingCompanyService::class)->create(['name' => 'Aramex', 'status' => 'ACTIVE']);
        $item = app(ItemService::class)->create(['name' => 'Coat', 'status' => GrossItem::STATUS_ACTIVE]);
        app(StockService::class)->adjustIn($item, 30, GrossStockMovement::UNIT_PIECE, $manager);

        $invoices = app(SalesInvoiceService::class);
        $confirmed = $invoices->confirm($invoices->createDraft($manager, [
            'customer_name' => 'Sara',
            'customer_phone' => '0100',
            'shipping_company_id' => $company->id,
            'currency' => 'EGP',
            'shipping_fee' => 50,
        ], [[
            'item_id' => $item->id,
            'qty' => 10,
            'unit' => 'PIECE',
            'unit_price' => 350,
        ]]), $manager)->load(['lines.allocation']);

        app(AllocationService::class)->partialReturn($confirmed->lines->first()->allocation, 2, $manager);

        $cash = GrossTreasuryAccount::query()->where('kind', GrossTreasuryAccount::KIND_CASH)->firstOrFail();
        $invoices->collectCod($confirmed->fresh(), $manager, [
            'post_to_treasury' => true,
            'treasury_account_id' => $cash->id,
        ]);

        $built = app(ShippingCollectionStatementService::class)->build($company, ['currency' => 'EGP']);

        $this->assertSame('2800.00', $built['total_invoices']);
        $this->assertSame('2800.00', $built['total_collections']);
        $this->assertSame('0.00', $built['balance']);
        $this->assertSame('2800.00', $built['entries'][0]['debit']);
        $this->assertSame('0.00', $built['entries'][1]['balance']);
    }
}
