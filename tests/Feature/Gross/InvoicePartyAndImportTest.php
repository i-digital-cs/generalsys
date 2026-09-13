<?php

namespace Tests\Feature\Gross;

use App\Models\Gross\GrossCustomer;
use App\Models\Gross\GrossItem;
use App\Models\Gross\GrossPurchaseInvoice;
use App\Models\Gross\GrossSalesInvoice;
use App\Models\Gross\GrossShippingCompany;
use App\Models\Gross\GrossSupplier;
use App\Models\User;
use App\Services\Gross\ItemService;
use App\Services\Gross\PurchaseInvoiceService;
use App\Services\Gross\SalesInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class InvoicePartyAndImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_invoice_auto_creates_customer_and_shipping_company(): void
    {
        $manager = User::factory()->manager()->create();
        $item = app(ItemService::class)->create([
            'name' => 'Dress',
            'status' => GrossItem::STATUS_ACTIVE,
            'default_sale_price' => 100,
        ]);

        $this->assertSame(0, GrossCustomer::query()->count());
        $this->assertSame(0, GrossShippingCompany::query()->count());

        $invoice = app(SalesInvoiceService::class)->createDraft($manager, [
            'customer_name' => 'New Client',
            'customer_phone' => '01009998877',
            'customer_address' => 'Nasr City',
            'shipping_company_name' => 'Fast Courier',
            'currency' => 'EGP',
            'shipping_fee' => 20,
            'discount_amount' => 0,
            'discount_percent' => 0,
        ], [[
            'item_id' => $item->id,
            'qty' => 1,
            'unit' => 'PIECE',
            'unit_price' => 100,
            'discount_amount' => 0,
            'discount_percent' => 0,
        ]]);

        $this->assertDatabaseHas('gross_customers', [
            'name' => 'New Client',
            'phone' => '01009998877',
            'address' => 'Nasr City',
        ]);
        $this->assertDatabaseHas('gross_shipping_companies', [
            'name' => 'Fast Courier',
        ]);
        $this->assertNotNull($invoice->customer_id);
        $this->assertNotNull($invoice->shipping_company_id);
        $this->assertSame('Fast Courier', $invoice->shipping_company_name);
    }

    public function test_purchase_invoice_auto_creates_supplier(): void
    {
        $manager = User::factory()->manager()->create();
        $item = app(ItemService::class)->create([
            'name' => 'Fabric',
            'status' => GrossItem::STATUS_ACTIVE,
        ]);

        $this->assertSame(0, GrossSupplier::query()->count());

        $invoice = app(PurchaseInvoiceService::class)->createDraft($manager, [
            'supplier_name' => 'Mill Co',
            'supplier_phone' => '01550001111',
            'supplier_address' => '10th Ramadan',
            'currency' => 'EGP',
            'discount_amount' => 0,
            'discount_percent' => 0,
        ], [[
            'item_id' => $item->id,
            'qty' => 2,
            'unit' => 'PIECE',
            'buy_price' => 30,
            'sale_price' => 55,
            'discount_amount' => 0,
            'discount_percent' => 0,
        ]]);

        $this->assertDatabaseHas('gross_suppliers', [
            'name' => 'Mill Co',
            'phone' => '01550001111',
        ]);
        $this->assertNotNull($invoice->supplier_id);
    }

    public function test_sales_excel_import_creates_draft_with_parties(): void
    {
        $manager = User::factory()->manager()->create();
        $item = app(ItemService::class)->create([
            'name' => 'Shirt',
            'status' => GrossItem::STATUS_ACTIVE,
            'default_sale_price' => 80,
        ]);

        $path = storage_path('app/tmp/test-sales-import.xlsx');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        $sheet = new Spreadsheet;
        $sheet->getActiveSheet()->fromArray([
            [
                'invoice_group', 'customer_name', 'customer_phone', 'customer_address', 'shipping_company',
                'currency', 'shipping_fee', 'discount_amount', 'discount_percent',
                'item_code', 'qty', 'unit_price', 'line_discount_amount', 'line_discount_percent', 'notes',
            ],
            [
                'G1', 'Import Client', '01001110000', 'Giza', 'Import Ship',
                'EGP', '15', '0', '0',
                $item->code, '2', '80', '0', '0', 'note',
            ],
        ]);
        (new Xlsx($sheet))->save($path);
        $sheet->disconnectWorksheets();

        $upload = new UploadedFile(
            $path,
            'sales.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $this->actingAs($manager)
            ->post(route('gross.sales.import'), ['file' => $upload])
            ->assertRedirect(route('gross.sales.index'))
            ->assertSessionHas('success');

        $this->assertSame(1, GrossSalesInvoice::query()->count());
        $invoice = GrossSalesInvoice::query()->firstOrFail();
        $this->assertSame(GrossSalesInvoice::STATUS_DRAFT, $invoice->status);
        $this->assertSame(2, (int) $invoice->lines()->sum('qty'));
        $this->assertDatabaseHas('gross_customers', ['phone' => '01001110000']);
        $this->assertDatabaseHas('gross_shipping_companies', ['name' => 'Import Ship']);
    }

    public function test_purchase_excel_import_creates_draft_with_supplier(): void
    {
        $manager = User::factory()->manager()->create();
        $item = app(ItemService::class)->create([
            'name' => 'Thread',
            'status' => GrossItem::STATUS_ACTIVE,
        ]);

        $path = storage_path('app/tmp/test-purchase-import.xlsx');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        $sheet = new Spreadsheet;
        $sheet->getActiveSheet()->fromArray([
            [
                'invoice_group', 'supplier_name', 'supplier_phone', 'supplier_address',
                'currency', 'discount_amount', 'discount_percent',
                'item_code', 'qty', 'buy_price', 'sale_price', 'line_discount_amount', 'line_discount_percent', 'notes',
            ],
            [
                'P1', 'Import Mill', '01552223333', 'Alex',
                'EGP', '0', '0',
                $item->code, '3', '25', '40', '0', '0', '',
            ],
        ]);
        (new Xlsx($sheet))->save($path);
        $sheet->disconnectWorksheets();

        $upload = new UploadedFile(
            $path,
            'purchases.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $this->actingAs($manager)
            ->post(route('gross.purchases.import'), ['file' => $upload])
            ->assertRedirect(route('gross.purchases.index'))
            ->assertSessionHas('success');

        $this->assertSame(1, GrossPurchaseInvoice::query()->count());
        $this->assertDatabaseHas('gross_suppliers', ['phone' => '01552223333', 'name' => 'Import Mill']);
    }

    public function test_templates_download(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)
            ->get(route('gross.sales.template'))
            ->assertOk()
            ->assertHeader('content-disposition');

        $this->actingAs($manager)
            ->get(route('gross.purchases.template'))
            ->assertOk()
            ->assertHeader('content-disposition');
    }
}
