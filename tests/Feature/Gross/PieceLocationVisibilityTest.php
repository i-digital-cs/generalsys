<?php

namespace Tests\Feature\Gross;

use App\Models\Gross\GrossItem;
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

class PieceLocationVisibilityTest extends TestCase
{
    use RefreshDatabase;
    use ShipsGrossSalesToCourier;

    public function test_stock_index_shows_total_and_courier_breakdown(): void
    {
        $manager = User::factory()->manager()->create();
        $item = app(ItemService::class)->create([
            'name' => 'Visible Dress',
            'reorder_level' => 1,
            'status' => GrossItem::STATUS_ACTIVE,
        ]);
        app(StockService::class)->adjustIn($item, 20, GrossStockMovement::UNIT_PIECE, $manager);

        $company = app(ShippingCompanyService::class)->create(['name' => 'Bosta', 'status' => 'ACTIVE']);
        $confirmed = $this->shipToCourier(
            app(SalesInvoiceService::class)->createDraft($manager, [
                'customer_name' => 'Ali',
                'customer_phone' => '0100',
                'shipping_company_id' => $company->id,
                'currency' => 'EGP',
            ], [[
                'item_id' => $item->id,
                'qty' => 3,
                'unit' => 'PIECE',
                'unit_price' => 50,
            ]]),
            $manager,
        )->load(['lines.allocation']);

        app(AllocationService::class)->partialReturn($confirmed->lines->first()->allocation, 1, $manager);

        $this->withSession(['locale' => 'en'])->actingAs($manager)
            ->get(route('gross.stock.index'))
            ->assertOk()
            ->assertSee(__('gross.fields.total_pieces'), false)
            ->assertSee('>20<', false)
            ->assertSee('Bosta', false)
            ->assertSee('>3<', false)
            ->assertSee(__('gross.stock.waiting_client'), false)
            ->assertSee(__('gross.stock.refused_with_courier'), false);
    }

    public function test_stock_index_shows_refused_with_courier_not_waiting_client(): void
    {
        $manager = User::factory()->manager()->create();
        $item = app(ItemService::class)->create([
            'name' => 'Refused Dress',
            'reorder_level' => 1,
            'status' => GrossItem::STATUS_ACTIVE,
        ]);
        app(StockService::class)->adjustIn($item, 20, GrossStockMovement::UNIT_PIECE, $manager);

        $company = app(ShippingCompanyService::class)->create(['name' => 'Bosta', 'status' => 'ACTIVE']);
        $confirmed = $this->shipToCourier(
            app(SalesInvoiceService::class)->createDraft($manager, [
                'customer_name' => 'Refused Client',
                'customer_phone' => '0100',
                'shipping_company_id' => $company->id,
                'currency' => 'EGP',
            ], [[
                'item_id' => $item->id,
                'qty' => 5,
                'unit' => 'PIECE',
                'unit_price' => 50,
            ]]),
            $manager,
        );

        app(SalesInvoiceService::class)->refuse($confirmed->fresh(), $manager);

        $this->withSession(['locale' => 'ar'])->actingAs($manager)
            ->get(route('gross.stock.index'))
            ->assertOk()
            ->assertSee('>5<', false)
            ->assertSee(__('gross.stock.refused_with_courier'), false)
            ->assertDontSee(__('gross.stock.waiting_client'), false);
    }

    public function test_shipping_show_opens_returns_and_with_courier_modals(): void
    {
        $manager = User::factory()->manager()->create();
        $item = app(ItemService::class)->create([
            'name' => 'Modal Item',
            'reorder_level' => 1,
            'status' => GrossItem::STATUS_ACTIVE,
        ]);
        app(StockService::class)->adjustIn($item, 15, GrossStockMovement::UNIT_PIECE, $manager);

        $company = app(ShippingCompanyService::class)->create(['name' => 'Modal Courier', 'status' => 'ACTIVE']);
        $confirmed = $this->shipToCourier(
            app(SalesInvoiceService::class)->createDraft($manager, [
                'customer_name' => 'Customer',
                'customer_phone' => '0100',
                'shipping_company_id' => $company->id,
                'currency' => 'EGP',
            ], [[
                'item_id' => $item->id,
                'qty' => 5,
                'unit' => 'PIECE',
                'unit_price' => 10,
            ]]),
            $manager,
        )->load(['lines.allocation']);

        app(AllocationService::class)->partialReturn($confirmed->lines->first()->allocation, 2, $manager);

        $this->withSession(['locale' => 'en'])->actingAs($manager)
            ->get(route('gross.shipping.show', $company))
            ->assertOk()
            ->assertSee('shippingReturnsModal', false)
            ->assertSee('shippingWithCourierModal', false)
            ->assertSee(__('gross.shipping.stats_click_hint'), false)
            ->assertSee(__('gross.shipping.returns_modal_title'), false)
            ->assertSee(__('gross.shipping.with_courier_modal_title'), false)
            ->assertSee('Modal Item', false);
    }
}
