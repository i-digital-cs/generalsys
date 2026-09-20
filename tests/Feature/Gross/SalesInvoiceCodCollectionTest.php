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
use Tests\Support\ShipsGrossSalesToCourier;
use Tests\TestCase;

class SalesInvoiceCodCollectionTest extends TestCase
{
    use RefreshDatabase;
    use ShipsGrossSalesToCourier;

    public function test_ship_to_courier_with_courier_sets_cod_pending(): void
    {
        $manager = User::factory()->manager()->create();
        $company = app(ShippingCompanyService::class)->create(['name' => 'Bosta', 'status' => 'ACTIVE']);
        $item = app(ItemService::class)->create(['name' => 'Dress', 'status' => GrossItem::STATUS_ACTIVE]);
        app(StockService::class)->adjustIn($item, 5, GrossStockMovement::UNIT_PIECE, $manager);

        $confirmed = $this->shipToCourier(
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
        $confirmed = $this->shipToCourier(
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

        $alloc = $confirmed->lines->first()->allocation->fresh();
        $this->assertSame(3, $alloc->qty_returned_pieces);
        $this->assertSame(0, $alloc->qty_returned_received_pieces);
        $this->assertSame(3, $alloc->returnedPendingHandover());
        $this->assertSame(0, GrossStockMovement::query()->where('type', GrossStockMovement::TYPE_SALE_RETURN)->count());
    }

    public function test_collect_cod_and_receive_returned_pieces_are_independent(): void
    {
        $manager = User::factory()->manager()->create();
        $company = app(ShippingCompanyService::class)->create(['name' => 'Bosta', 'status' => 'ACTIVE']);
        $item = app(ItemService::class)->create(['name' => 'Pants', 'status' => GrossItem::STATUS_ACTIVE]);
        app(StockService::class)->adjustIn($item, 10, GrossStockMovement::UNIT_PIECE, $manager);

        $service = app(SalesInvoiceService::class);
        $confirmed = $this->shipToCourier(
            $service->createDraft($manager, [
                'customer_name' => 'Omar',
                'customer_phone' => '0109',
                'shipping_company_id' => $company->id,
                'currency' => 'EGP',
                'shipping_fee' => 30,
            ], [[
                'item_id' => $item->id,
                'qty' => 10,
                'unit' => 'PIECE',
                'unit_price' => 50,
            ]]),
            $manager,
        )->load(['lines.allocation']);

        app(AllocationService::class)->partialReturn($confirmed->lines->first()->allocation, 2, $manager);

        $this->actingAs($manager)
            ->post(route('gross.sales.collect-cod', $confirmed), [])
            ->assertRedirect();

        $alloc = $confirmed->lines->first()->allocation->fresh();
        $this->assertSame(GrossSalesInvoice::COD_COLLECTED, $confirmed->fresh()->cod_collection_status);
        $this->assertSame(0, $alloc->qty_returned_received_pieces);
        $this->assertSame(2, $alloc->returnedPendingHandover());

        $this->actingAs($manager)
            ->post(route('gross.sales.receive-returned', $confirmed))
            ->assertRedirect(route('gross.sales.show', $confirmed));

        $alloc->refresh();
        $this->assertSame(2, $alloc->qty_returned_received_pieces);
        $this->assertSame(0, $alloc->returnedPendingHandover());
        $this->assertSame(1, GrossStockMovement::query()->where('type', GrossStockMovement::TYPE_SALE_RETURN)->count());
    }

    public function test_receive_returned_pieces_before_collect_cod(): void
    {
        $manager = User::factory()->manager()->create();
        $company = app(ShippingCompanyService::class)->create(['name' => 'Aramex', 'status' => 'ACTIVE']);
        $item = app(ItemService::class)->create(['name' => 'Hat', 'status' => GrossItem::STATUS_ACTIVE]);
        app(StockService::class)->adjustIn($item, 5, GrossStockMovement::UNIT_PIECE, $manager);

        $service = app(SalesInvoiceService::class);
        $confirmed = $this->shipToCourier(
            $service->createDraft($manager, [
                'customer_name' => 'Lina',
                'customer_phone' => '0110',
                'shipping_company_id' => $company->id,
                'currency' => 'EGP',
                'shipping_fee' => 10,
            ], [[
                'item_id' => $item->id,
                'qty' => 5,
                'unit' => 'PIECE',
                'unit_price' => 40,
            ]]),
            $manager,
        )->load(['lines.allocation']);

        app(AllocationService::class)->partialReturn($confirmed->lines->first()->allocation, 1, $manager);

        $service->receiveReturnedPiecesFromCourier($confirmed->fresh(), $manager);

        $alloc = $confirmed->lines->first()->allocation->fresh();
        $this->assertSame(1, $alloc->qty_returned_received_pieces);
        $this->assertSame(GrossSalesInvoice::COD_PENDING, $confirmed->fresh()->cod_collection_status);

        $collected = $service->collectCod($confirmed->fresh(), $manager);
        $this->assertSame(GrossSalesInvoice::COD_COLLECTED, $collected->cod_collection_status);
        $this->assertSame('160.00', $collected->cod_collected_amount);
    }

    public function test_receive_partial_qty_per_line_independently(): void
    {
        $manager = User::factory()->manager()->create();
        $company = app(ShippingCompanyService::class)->create(['name' => 'J&T', 'status' => 'ACTIVE']);
        $itemA = app(ItemService::class)->create(['name' => 'Dress A', 'status' => GrossItem::STATUS_ACTIVE]);
        $itemB = app(ItemService::class)->create(['name' => 'Dress B', 'status' => GrossItem::STATUS_ACTIVE]);
        app(StockService::class)->adjustIn($itemA, 10, GrossStockMovement::UNIT_PIECE, $manager);
        app(StockService::class)->adjustIn($itemB, 10, GrossStockMovement::UNIT_PIECE, $manager);

        $service = app(SalesInvoiceService::class);
        $confirmed = $this->shipToCourier(
            $service->createDraft($manager, [
                'customer_name' => 'Mona',
                'customer_phone' => '0111',
                'shipping_company_id' => $company->id,
                'currency' => 'EGP',
                'shipping_fee' => 20,
            ], [
                ['item_id' => $itemA->id, 'qty' => 5, 'unit' => 'PIECE', 'unit_price' => 100],
                ['item_id' => $itemB->id, 'qty' => 4, 'unit' => 'PIECE', 'unit_price' => 80],
            ]),
            $manager,
        )->load(['lines.allocation']);

        $allocA = $confirmed->lines->firstWhere('item_id', $itemA->id)->allocation;
        $allocB = $confirmed->lines->firstWhere('item_id', $itemB->id)->allocation;
        app(AllocationService::class)->partialReturn($allocA, 3, $manager);
        app(AllocationService::class)->partialReturn($allocB, 2, $manager);

        $this->actingAs($manager)
            ->post(route('gross.sales.receive-returned-line', $confirmed), [
                'allocation_id' => $allocA->id,
                'qty_pieces' => 1,
            ])
            ->assertRedirect(route('gross.sales.show', $confirmed));

        $allocA->refresh();
        $allocB->refresh();
        $this->assertSame(1, $allocA->qty_returned_received_pieces);
        $this->assertSame(2, $allocA->returnedPendingHandover());
        $this->assertSame(0, $allocB->qty_returned_received_pieces);
        $this->assertSame(2, $allocB->returnedPendingHandover());

        $this->actingAs($manager)
            ->post(route('gross.sales.receive-returned-line', $confirmed), [
                'allocation_id' => $allocB->id,
                'qty_pieces' => 2,
            ])
            ->assertRedirect();

        $allocB->refresh();
        $this->assertSame(2, $allocB->qty_returned_received_pieces);
        $this->assertSame(0, $allocB->returnedPendingHandover());
        $this->assertSame(2, $allocA->fresh()->returnedPendingHandover());
    }

    public function test_refuse_sets_cod_lost(): void
    {
        $manager = User::factory()->manager()->create();
        $company = app(ShippingCompanyService::class)->create(['name' => 'X', 'status' => 'ACTIVE']);
        $item = app(ItemService::class)->create(['name' => 'Coat', 'status' => GrossItem::STATUS_ACTIVE]);
        app(StockService::class)->adjustIn($item, 5, GrossStockMovement::UNIT_PIECE, $manager);

        $service = app(SalesInvoiceService::class);
        $confirmed = $this->shipToCourier(
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
