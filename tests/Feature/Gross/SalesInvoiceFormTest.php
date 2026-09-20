<?php

namespace Tests\Feature\Gross;

use App\Models\Gross\GrossItem;
use App\Models\Gross\GrossSalesInvoice;
use App\Models\Gross\GrossShippingCompany;
use App\Models\Gross\GrossStockMovement;
use App\Models\User;
use App\Services\Gross\ItemService;
use App\Services\Gross\SalesInvoiceService;
use App\Services\Gross\ShippingCompanyService;
use App\Services\Gross\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesInvoiceFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_form_uses_typeahead_item_search_controls(): void
    {
        $manager = User::factory()->manager()->create();
        $items = app(ItemService::class);
        for ($i = 1; $i <= 3; $i++) {
            $items->create([
                'name' => "Item {$i}",
                'status' => GrossItem::STATUS_ACTIVE,
                'default_sale_price' => 10 * $i,
            ]);
        }

        $this->withSession(['locale' => 'en'])->actingAs($manager)
            ->get(route('gross.sales.create'))
            ->assertOk()
            ->assertSee('Add item', false)
            ->assertSee('Type item name or code', false)
            ->assertSee('Type in each line to search items', false)
            ->assertSee('Invoice discount', false)
            ->assertSee(route('gross.items.search', [], false), false)
            ->assertSee('data-line-item-thumb', false)
            ->assertDontSee('Item 1', false)
            ->assertDontSee('Item 3', false);
    }

    public function test_store_multi_line_with_line_and_invoice_percent_discounts(): void
    {
        $manager = User::factory()->manager()->create();
        $stock = app(StockService::class);
        $a = app(ItemService::class)->create([
            'name' => 'A',
            'status' => GrossItem::STATUS_ACTIVE,
            'default_sale_price' => 100,
        ]);
        $b = app(ItemService::class)->create([
            'name' => 'B',
            'status' => GrossItem::STATUS_ACTIVE,
            'default_sale_price' => 50,
        ]);
        $stock->adjustIn($a, 20, GrossStockMovement::UNIT_PIECE, $manager);
        $stock->adjustIn($b, 20, GrossStockMovement::UNIT_PIECE, $manager);
        $shippingId = app(ShippingCompanyService::class)->create([
            'name' => 'Ship',
            'status' => 'ACTIVE',
        ])->id;

        $this->actingAs($manager)
            ->post(route('gross.sales.store'), [
                'customer_name' => 'Client',
                'customer_phone' => '0100',
                'shipping_company_id' => $shippingId,
                'currency' => 'EGP',
                'shipping_fee' => 10,
                'discount_mode' => 'percent',
                'discount_percent' => 10,
                'discount_amount' => 999,
                'lines' => [
                    [
                        'item_id' => $a->id,
                        'qty' => 2,
                        'unit' => 'PIECE',
                        'unit_price' => 100,
                        'discount_mode' => 'percent',
                        'discount_percent' => 10,
                        'discount_amount' => 0,
                    ],
                    [
                        'item_id' => $b->id,
                        'qty' => 1,
                        'unit' => 'PIECE',
                        'unit_price' => 50,
                        'discount_mode' => 'amount',
                        'discount_amount' => 5,
                        'discount_percent' => 0,
                    ],
                ],
            ])
            ->assertRedirect();

        $invoice = GrossSalesInvoice::query()->firstOrFail();
        // Line A: 200 - 10% = 180; Line B: 50 - 5 = 45; subtotal 225
        // Invoice 10% of 225 = 22.5; total = 225 - 22.5 + 10 = 212.5
        $this->assertSame('225.00', (string) $invoice->subtotal);
        $this->assertSame('10.00', (string) $invoice->discount_percent);
        $this->assertSame('22.50', (string) $invoice->discount_amount);
        $this->assertSame('212.50', (string) $invoice->total);
        $this->assertCount(2, $invoice->lines);
        $this->assertSame('180.00', (string) $invoice->lines->firstWhere('item_id', $a->id)->line_total);
        $this->assertSame('45.00', (string) $invoice->lines->firstWhere('item_id', $b->id)->line_total);
    }

    public function test_invoice_amount_discount_clears_percent(): void
    {
        $manager = User::factory()->manager()->create();
        $item = app(ItemService::class)->create([
            'name' => 'Solo',
            'status' => GrossItem::STATUS_ACTIVE,
        ]);
        app(StockService::class)->adjustIn($item, 5, GrossStockMovement::UNIT_PIECE, $manager);
        $shippingId = GrossShippingCompany::query()->create([
            'name' => 'C',
            'status' => 'ACTIVE',
        ])->id;

        $invoice = app(SalesInvoiceService::class)->createDraft($manager, [
            'customer_name' => 'X',
            'customer_phone' => '01',
            'shipping_company_id' => $shippingId,
            'currency' => 'EGP',
            'shipping_fee' => 0,
            'discount_amount' => 15,
            'discount_percent' => 0,
        ], [[
            'item_id' => $item->id,
            'qty' => 1,
            'unit' => 'PIECE',
            'unit_price' => 100,
            'discount_amount' => 0,
            'discount_percent' => 0,
        ]]);

        $this->assertSame('0.00', (string) $invoice->discount_percent);
        $this->assertSame('15.00', (string) $invoice->discount_amount);
        $this->assertSame('85.00', (string) $invoice->total);
    }
}
