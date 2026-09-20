<?php

namespace Tests\Unit\Gross;

use App\Models\Gross\GrossItem;
use App\Models\Gross\GrossStockMovement;
use App\Models\User;
use App\Services\Gross\AllocationService;
use App\Services\Gross\ItemService;
use App\Services\Gross\PieceLocationService;
use App\Services\Gross\SalesInvoiceService;
use App\Services\Gross\ShippingCompanyService;
use App\Services\Gross\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ShipsGrossSalesToCourier;
use Tests\TestCase;

class PieceLocationServiceTest extends TestCase
{
    use RefreshDatabase;
    use ShipsGrossSalesToCourier;

    public function test_courier_breakdown_groups_by_company_for_confirmed_invoices(): void
    {
        $manager = User::factory()->manager()->create();
        $item = app(ItemService::class)->create([
            'name' => 'Dress',
            'reorder_level' => 1,
            'status' => GrossItem::STATUS_ACTIVE,
        ]);
        app(StockService::class)->adjustIn($item, 30, GrossStockMovement::UNIT_PIECE, $manager);

        $companyA = app(ShippingCompanyService::class)->create(['name' => 'Company A', 'status' => 'ACTIVE']);
        $companyB = app(ShippingCompanyService::class)->create(['name' => 'Company B', 'status' => 'ACTIVE']);

        $invoices = app(SalesInvoiceService::class);
        foreach ([[$companyA, 4], [$companyB, 2]] as [$company, $qty]) {
            $draft = $invoices->createDraft($manager, [
                'customer_name' => $company->name,
                'customer_phone' => '0100',
                'shipping_company_id' => $company->id,
                'currency' => 'EGP',
            ], [[
                'item_id' => $item->id,
                'qty' => $qty,
                'unit' => 'PIECE',
                'unit_price' => 10,
            ]]);
            $this->shipToCourier($draft, $manager);
        }

        $breakdown = app(PieceLocationService::class)->courierBreakdownByItemIds([$item->id]);

        $this->assertCount(2, $breakdown[$item->id]);
        $this->assertSame(6, array_sum(array_column($breakdown[$item->id], 'qty')));
    }

    public function test_partial_return_removes_qty_from_courier_breakdown(): void
    {
        $manager = User::factory()->manager()->create();
        $item = app(ItemService::class)->create([
            'name' => 'Shirt',
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
        ], [[
            'item_id' => $item->id,
            'qty' => 10,
            'unit' => 'PIECE',
            'unit_price' => 10,
        ]]), $manager)->load(['lines.allocation']);

        $locations = app(PieceLocationService::class);
        $this->assertSame(10, $locations->courierBreakdownByItemIds([$item->id])[$item->id][0]['qty']);

        app(AllocationService::class)->partialReturn($confirmed->lines->first()->allocation, 3, $manager);

        $this->assertSame(10, $locations->courierBreakdownByItemIds([$item->id])[$item->id][0]['qty']);
        $this->assertSame(7, $locations->courierBreakdownByItemIds([$item->id])[$item->id][0]['awaiting_delivery']);
        $this->assertSame(3, $locations->courierBreakdownByItemIds([$item->id])[$item->id][0]['refused_with_courier']);
        $this->assertCount(1, $locations->courierBreakdownByItemIds([$item->id])[$item->id][0]['invoices']);
        $this->assertSame(10, $locations->courierBreakdownByItemIds([$item->id])[$item->id][0]['invoices'][0]['qty']);
        $this->assertSame(20, $locations->totalTrackedPieces($item->fresh()->balance));
    }
}
