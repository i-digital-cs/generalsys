<?php

namespace Tests\Feature\Gross;

use App\Models\Gross\GrossItem;
use App\Models\Gross\GrossShippingCompany;
use App\Models\Gross\GrossStockMovement;
use App\Models\User;
use App\Services\Gross\AllocationService;
use App\Services\Gross\ItemService;
use App\Services\Gross\SalesInvoiceService;
use App\Services\Gross\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesInvoiceShowTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_page_displays_piece_and_amount_breakdown_after_return(): void
    {
        $manager = User::factory()->manager()->create();
        $item = app(ItemService::class)->create([
            'name' => 'Tracked Dress',
            'status' => GrossItem::STATUS_ACTIVE,
        ]);
        app(StockService::class)->adjustIn($item, 20, GrossStockMovement::UNIT_PIECE, $manager);
        $shipping = GrossShippingCompany::query()->create(['name' => 'Bosta', 'status' => 'ACTIVE']);

        $invoice = app(SalesInvoiceService::class)->createDraft($manager, [
            'customer_name' => 'Ali',
            'customer_phone' => '0100',
            'shipping_company_id' => $shipping->id,
            'currency' => 'EGP',
            'shipping_fee' => 0,
        ], [[
            'item_id' => $item->id,
            'qty' => 10,
            'unit' => 'PIECE',
            'unit_price' => 100,
        ]]);

        $confirmed = app(SalesInvoiceService::class)->confirm($invoice, $manager)->load(['lines.allocation']);
        app(AllocationService::class)->partialReturn($confirmed->lines->first()->allocation, 3, $manager);

        $this->withSession(['locale' => 'en'])->actingAs($manager)
            ->get(route('gross.sales.show', $confirmed))
            ->assertOk()
            ->assertSee(__('gross.sales.qty_shipped'), false)
            ->assertSee(__('gross.sales.piece_tracking_title'), false)
            ->assertSee(__('gross.sales.awaiting_delivery'), false)
            ->assertSee(__('gross.sales.stock_impact_title'), false)
            ->assertSee(__('gross.sales.net_collectible'), false)
            ->assertSee(__('gross.sales.piece_equation_shipped'), false)
            ->assertSee('>10<', false)
            ->assertSee('>3<', false)
            ->assertSee('>7<', false)
            ->assertSee('10 on invoice = 0 delivered + 10 still with courier (3 refused awaiting handover + 7 awaiting delivery)', false)
            ->assertSee('max 7 with courier', false);
    }
}
