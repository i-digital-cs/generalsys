<?php

namespace Tests\Feature\Gross;

use App\Models\Gross\GrossItem;
use App\Models\Gross\GrossSupplier;
use App\Models\User;
use App\Services\Gross\ItemService;
use App\Services\Gross\PurchaseInvoiceService;
use App\Services\Gross\PurchaseReturnService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class PurchaseInvoiceDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_page_displays_net_payable_and_exports(): void
    {
        $manager = User::factory()->manager()->create();
        $supplier = GrossSupplier::query()->create(['name' => 'Alex Fabrics', 'phone' => '01119876543']);
        $item = app(ItemService::class)->create([
            'name' => 'Summer Dress',
            'size' => 'L',
            'status' => GrossItem::STATUS_ACTIVE,
        ]);

        $purchases = app(PurchaseInvoiceService::class);
        $invoice = $purchases->confirm($purchases->createDraft($manager, [
            'supplier_id' => $supplier->id,
            'supplier_name' => $supplier->name,
            'supplier_phone' => $supplier->phone,
            'currency' => 'EGP',
        ], [[
            'item_id' => $item->id,
            'qty' => 50,
            'unit' => 'PIECE',
            'buy_price' => 300,
            'sale_price' => 350,
        ]]), $manager)->load('lines');

        app(PurchaseReturnService::class)->create($manager, $invoice, [[
            'purchase_invoice_line_id' => $invoice->lines->first()->id,
            'qty' => 22,
        ]]);

        $invoice = $invoice->fresh();

        $this->withSession(['locale' => 'en'])->actingAs($manager)
            ->get(route('gross.purchases.show', $invoice))
            ->assertOk()
            ->assertSee($invoice->invoice_number, false)
            ->assertSee('Alex Fabrics', false)
            ->assertSee('Net payable to supplier', false)
            ->assertSee('8,400.00', false)
            ->assertSee(route('gross.purchases.pdf', $invoice), false)
            ->assertSee(route('gross.purchases.excel', $invoice), false);

        $this->actingAs($manager)
            ->get(route('gross.purchases.pdf', $invoice))
            ->assertOk()
            ->assertHeader('content-disposition');

        $response = $this->actingAs($manager)
            ->get(route('gross.purchases.excel', $invoice));
        $response->assertOk()->assertHeader('content-disposition');

        $tmp = tempnam(sys_get_temp_dir(), 'pur-xlsx-').'.xlsx';
        file_put_contents($tmp, $response->streamedContent());
        $sheet = IOFactory::load($tmp)->getActiveSheet();
        @unlink($tmp);

        $this->assertSame($invoice->invoice_number, $sheet->getCell('B1')->getValue());
        $this->assertEqualsWithDelta(6600.0, (float) $sheet->getCell('B10')->getValue(), 0.001);
        $this->assertEqualsWithDelta(8400.0, (float) $sheet->getCell('B13')->getValue(), 0.001);
    }
}
