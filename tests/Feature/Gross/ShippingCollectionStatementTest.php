<?php

namespace Tests\Feature\Gross;

use App\Models\Gross\GrossItem;
use App\Models\Gross\GrossStockMovement;
use App\Models\User;
use App\Services\Gross\ItemService;
use App\Services\Gross\SalesInvoiceService;
use App\Services\Gross\ShippingCompanyService;
use App\Services\Gross\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShippingCollectionStatementTest extends TestCase
{
    use RefreshDatabase;

    public function test_statement_page_and_currency_filter(): void
    {
        $manager = User::factory()->manager()->create();
        $company = app(ShippingCompanyService::class)->create(['name' => 'Bosta', 'status' => 'ACTIVE']);
        $item = app(ItemService::class)->create(['name' => 'Dress', 'status' => GrossItem::STATUS_ACTIVE]);
        app(StockService::class)->adjustIn($item, 10, GrossStockMovement::UNIT_PIECE, $manager);

        $invoices = app(SalesInvoiceService::class);
        $egp = $invoices->confirm($invoices->createDraft($manager, [
            'customer_name' => 'EGP Customer',
            'customer_phone' => '0100',
            'shipping_company_id' => $company->id,
            'currency' => 'EGP',
            'shipping_fee' => 0,
        ], [[
            'item_id' => $item->id,
            'qty' => 2,
            'unit' => 'PIECE',
            'unit_price' => 50,
        ]]), $manager);

        $aed = $invoices->confirm($invoices->createDraft($manager, [
            'customer_name' => 'AED Customer',
            'customer_phone' => '0101',
            'shipping_company_id' => $company->id,
            'currency' => 'AED',
            'shipping_fee' => 0,
        ], [[
            'item_id' => $item->id,
            'qty' => 1,
            'unit' => 'PIECE',
            'unit_price' => 100,
        ]]), $manager);

        $this->withSession(['locale' => 'en'])->actingAs($manager)
            ->get(route('gross.shipping.statement', $company))
            ->assertOk()
            ->assertSee(__('gross.shipping.statement_title', ['name' => $company->name]), false)
            ->assertSee(__('gross.shipping.statement_balance_hint'), false)
            ->assertSee($egp->invoice_number, false)
            ->assertDontSee($aed->invoice_number, false);

        $this->actingAs($manager)
            ->get(route('gross.shipping.show', $company))
            ->assertOk()
            ->assertSee(route('gross.shipping.statement', $company), false);

        $this->actingAs($manager)
            ->get(route('gross.shipping.index'))
            ->assertOk()
            ->assertSee(route('gross.shipping.statement', $company), false);
    }

    public function test_date_filter_narrows_statement_lines(): void
    {
        $manager = User::factory()->manager()->create();
        $company = app(ShippingCompanyService::class)->create(['name' => 'Filter Co', 'status' => 'ACTIVE']);
        $item = app(ItemService::class)->create(['name' => 'Shirt', 'status' => GrossItem::STATUS_ACTIVE]);
        app(StockService::class)->adjustIn($item, 5, GrossStockMovement::UNIT_PIECE, $manager);

        app(SalesInvoiceService::class)->confirm(
            app(SalesInvoiceService::class)->createDraft($manager, [
                'customer_name' => 'Today',
                'customer_phone' => '0100',
                'shipping_company_id' => $company->id,
                'currency' => 'EGP',
            ], [[
                'item_id' => $item->id,
                'qty' => 1,
                'unit' => 'PIECE',
                'unit_price' => 80,
            ]]),
            $manager,
        );

        $today = now()->toDateString();
        $future = now()->addDays(30)->toDateString();

        $this->actingAs($manager)
            ->get(route('gross.shipping.statement', ['shipping' => $company, 'from' => $today, 'to' => $today]))
            ->assertOk()
            ->assertSee('80.00', false);

        $this->actingAs($manager)
            ->get(route('gross.shipping.statement', ['shipping' => $company, 'from' => $future]))
            ->assertOk()
            ->assertSee(__('gross.shipping.statement_empty'), false);
    }
}
