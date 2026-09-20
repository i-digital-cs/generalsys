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
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Support\ShipsGrossSalesToCourier;
use Tests\TestCase;

class SalesInvoiceDocumentTest extends TestCase
{
    use RefreshDatabase;
    use ShipsGrossSalesToCourier;

    public function test_show_page_is_readable_invoice_with_exports(): void
    {
        $manager = User::factory()->manager()->create([
            'name_en' => 'Sales Manager',
            'name_ar' => 'مدير مبيعات',
        ]);
        $item = app(ItemService::class)->create([
            'name' => 'Dress',
            'size' => 'L',
            'status' => GrossItem::STATUS_ACTIVE,
            'default_sale_price' => 100,
        ]);
        app(StockService::class)->adjustIn($item, 10, GrossStockMovement::UNIT_PIECE, $manager);
        $shipping = GrossShippingCompany::query()->create([
            'name' => 'Bosta',
            'status' => 'ACTIVE',
        ]);

        $invoice = app(SalesInvoiceService::class)->createDraft($manager, [
            'customer_name' => 'Ali',
            'customer_phone' => '0100',
            'shipping_company_id' => $shipping->id,
            'sales_user_id' => $manager->id,
            'currency' => 'EGP',
            'shipping_fee' => 25,
            'notes' => 'Handle with care',
        ], [[
            'item_id' => $item->id,
            'qty' => 2,
            'unit' => 'PIECE',
            'unit_price' => 100,
            'discount_amount' => 0,
            'discount_percent' => 0,
        ]]);

        $this->shipToCourier($invoice, $manager);
        $invoice = $invoice->fresh();

        $this->withSession(['locale' => 'en'])->actingAs($manager)
            ->get(route('gross.sales.show', $invoice))
            ->assertOk()
            ->assertSee($invoice->invoice_number, false)
            ->assertSee('Confirmed', false)
            ->assertSee('Sales Manager', false)
            ->assertSee('L', false)
            ->assertSee('Grand total', false)
            ->assertSee('Handle with care', false)
            ->assertSee(route('gross.sales.pdf', $invoice), false)
            ->assertSee(route('gross.sales.excel', $invoice), false)
            ->assertSee('bi-filetype-pdf', false)
            ->assertSee('Collect COD', false);

        $this->actingAs($manager)
            ->get(route('gross.sales.pdf', $invoice))
            ->assertOk()
            ->assertHeader('content-disposition');

        $this->actingAs($manager)
            ->get(route('gross.sales.excel', $invoice))
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_exports_include_net_pieces_and_amounts_after_partial_return(): void
    {
        $manager = User::factory()->manager()->create();
        $item = app(ItemService::class)->create([
            'name' => 'Export Dress',
            'status' => GrossItem::STATUS_ACTIVE,
        ]);
        app(StockService::class)->adjustIn($item, 20, GrossStockMovement::UNIT_PIECE, $manager);

        $invoice = app(SalesInvoiceService::class)->createDraft($manager, [
            'customer_name' => 'Ali',
            'customer_phone' => '0100',
            'currency' => 'EGP',
            'shipping_fee' => 50,
        ], [[
            'item_id' => $item->id,
            'qty' => 10,
            'unit' => 'PIECE',
            'unit_price' => 100,
        ]]);

        $confirmed = $this->shipToCourier($invoice, $manager)->load(['lines.allocation']);
        app(AllocationService::class)->partialReturn($confirmed->lines->first()->allocation, 3, $manager);

        $excelResponse = $this->actingAs($manager)
            ->get(route('gross.sales.excel', $confirmed))
            ->assertOk();

        $tmp = tempnam(sys_get_temp_dir(), 'gross-xlsx-').'.xlsx';
        file_put_contents($tmp, $excelResponse->streamedContent());
        $sheet = IOFactory::load($tmp)->getActiveSheet();
        @unlink($tmp);

        $this->assertEqualsWithDelta(300.0, (float) $sheet->getCell('B13')->getValue(), 0.001);
        $this->assertEqualsWithDelta(700.0, (float) $sheet->getCell('B14')->getValue(), 0.001);
        $this->assertEqualsWithDelta(750.0, (float) $sheet->getCell('B16')->getValue(), 0.001);
        $this->assertSame(10, (int) $sheet->getCell('D20')->getValue());
        $this->assertSame(3, (int) $sheet->getCell('E20')->getValue());
        $this->assertEqualsWithDelta(700.0, (float) $sheet->getCell('N20')->getValue(), 0.001);

        $this->actingAs($manager)
            ->get(route('gross.sales.pdf', $confirmed))
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_create_form_has_sales_person_and_live_totals(): void
    {
        $manager = User::factory()->manager()->create();
        User::factory()->create([
            'role' => User::ROLE_SALES,
            'name_en' => 'Rep One',
            'status' => User::STATUS_ACTIVE,
        ]);

        $this->withSession(['locale' => 'en'])->actingAs($manager)
            ->get(route('gross.sales.create'))
            ->assertOk()
            ->assertSee('Sales person', false)
            ->assertSee('Rep One', false)
            ->assertSee('data-gross-totals', false)
            ->assertSee('data-line-total', false);
    }
}
