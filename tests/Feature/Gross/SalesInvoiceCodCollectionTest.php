<?php

namespace Tests\Feature\Gross;

use App\Models\Gross\GrossItem;
use App\Models\Gross\GrossSalesInvoice;
use App\Models\Gross\GrossStockMovement;
use App\Models\User;
use App\Services\Gross\AllocationService;
use App\Services\Gross\ItemService;
use App\Services\Gross\SalesInvoiceService;
use App\Services\Gross\ShippingCompanyService;
use App\Services\Gross\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesInvoiceCodCollectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirm_with_courier_sets_cod_pending(): void
    {
        $manager = User::factory()->manager()->create();
        $company = app(ShippingCompanyService::class)->create(['name' => 'Bosta', 'status' => 'ACTIVE']);
        $item = app(ItemService::class)->create(['name' => 'Dress', 'status' => GrossItem::STATUS_ACTIVE]);
        app(StockService::class)->adjustIn($item, 5, GrossStockMovement::UNIT_PIECE, $manager);

        $confirmed = app(SalesInvoiceService::class)->confirm(
            app(SalesInvoiceService::class)->createDraft($manager, [
                'customer_name' => 'Ali',
                'customer_phone' => '0100',
                'shipping_company_id' => $company->id,
                'currency' => 'EGP',
                'shipping_fee' => 20,
            ], [[
                'item_id' => $item->id,
                'qty' => 2,
                'unit' => 'PIECE',
                'unit_price' => 50,
            ]]),
            $manager,
        );

        $this->assertSame(GrossSalesInvoice::COD_PENDING, $confirmed->cod_collection_status);
        $this->assertSame('120.00', $confirmed->total);
    }

    public function test_partial_return_reduces_collectible_on_collect(): void
    {
        $manager = User::factory()->manager()->create();
        $company = app(ShippingCompanyService::class)->create(['name' => 'Courier', 'status' => 'ACTIVE']);
        $item = app(ItemService::class)->create(['name' => 'Shirt', 'status' => GrossItem::STATUS_ACTIVE]);
        app(StockService::class)->adjustIn($item, 10, GrossStockMovement::UNIT_PIECE, $manager);

        $service = app(SalesInvoiceService::class);
        $confirmed = $service->confirm(
            $service->createDraft($manager, [
                'customer_name' => 'Sara',
                'customer_phone' => '0101',
                'shipping_company_id' => $company->id,
                'currency' => 'EGP',
                'shipping_fee' => 50,
            ], [[
                'item_id' => $item->id,
                'qty' => 10,
                'unit' => 'PIECE',
                'unit_price' => 100,
            ]]),
            $manager,
        )->load(['lines.allocation']);

        app(AllocationService::class)->partialReturn($confirmed->lines->first()->allocation, 3, $manager);

        $collected = $service->collectCod($confirmed->fresh(), $manager);

        $this->assertSame(GrossSalesInvoice::COD_COLLECTED, $collected->cod_collection_status);
        $this->assertSame('700.00', $collected->cod_collected_amount);
    }

    public function test_refuse_sets_cod_lost(): void
    {
        $manager = User::factory()->manager()->create();
        $company = app(ShippingCompanyService::class)->create(['name' => 'X', 'status' => 'ACTIVE']);
        $item = app(ItemService::class)->create(['name' => 'Coat', 'status' => GrossItem::STATUS_ACTIVE]);
        app(StockService::class)->adjustIn($item, 5, GrossStockMovement::UNIT_PIECE, $manager);

        $service = app(SalesInvoiceService::class);
        $confirmed = $service->confirm(
            $service->createDraft($manager, [
                'customer_name' => 'Y',
                'customer_phone' => '0102',
                'shipping_company_id' => $company->id,
                'currency' => 'EGP',
                'shipping_fee' => 15,
            ], [[
                'item_id' => $item->id,
                'qty' => 2,
                'unit' => 'PIECE',
                'unit_price' => 40,
            ]]),
            $manager,
        );

        $refused = $service->refuse($confirmed, $manager);

        $this->assertSame(GrossSalesInvoice::COD_LOST, $refused->cod_collection_status);
        $this->assertSame(GrossSalesInvoice::SHIPPING_FEE_PENDING, $refused->shipping_fee_status);
    }
}
