<?php

namespace Tests\Feature\Gross;

use App\Models\Gross\GrossSalesInvoice;
use App\Models\Gross\GrossShippingCompany;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ShippingCompanyCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_filters_and_shows_controls(): void
    {
        $manager = User::factory()->manager()->create();
        GrossShippingCompany::query()->create([
            'name' => 'Bosta',
            'phone' => '01001110000',
            'status' => GrossShippingCompany::STATUS_ACTIVE,
        ]);
        GrossShippingCompany::query()->create([
            'name' => 'Aramex',
            'phone' => '01112223333',
            'status' => GrossShippingCompany::STATUS_ACTIVE,
        ]);

        $this->actingAs($manager)
            ->get(route('gross.shipping.index', ['q' => 'Aramex']))
            ->assertOk()
            ->assertSee('Aramex', false)
            ->assertDontSee('01001110000', false);

        $this->withSession(['locale' => 'en'])->actingAs($manager)
            ->get(route('gross.shipping.index'))
            ->assertOk()
            ->assertSee('bi-eye', false)
            ->assertSee('bi-pencil', false)
            ->assertSee('bi-trash', false)
            ->assertSee('Import from Excel', false);
    }

    public function test_can_update_and_delete_shipping_company(): void
    {
        $manager = User::factory()->manager()->create();
        $company = GrossShippingCompany::query()->create([
            'name' => 'Old Courier',
            'phone' => '01000000001',
            'status' => GrossShippingCompany::STATUS_ACTIVE,
        ]);

        $this->actingAs($manager)
            ->put(route('gross.shipping.update', $company), [
                'name' => 'New Courier',
                'phone' => '01000000002',
                'address' => 'Alex',
                'notes' => 'Note',
                'status' => GrossShippingCompany::STATUS_INACTIVE,
            ])
            ->assertRedirect(route('gross.shipping.index'));

        $this->assertDatabaseHas('gross_shipping_companies', [
            'id' => $company->id,
            'name' => 'New Courier',
            'status' => GrossShippingCompany::STATUS_INACTIVE,
        ]);

        $this->actingAs($manager)
            ->delete(route('gross.shipping.destroy', $company))
            ->assertRedirect(route('gross.shipping.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('gross_shipping_companies', ['id' => $company->id]);
    }

    public function test_cannot_delete_shipping_in_use(): void
    {
        $manager = User::factory()->manager()->create();
        $company = GrossShippingCompany::query()->create([
            'name' => 'Linked',
            'status' => GrossShippingCompany::STATUS_ACTIVE,
        ]);

        GrossSalesInvoice::query()->create([
            'invoice_number' => 'INV-SHIP-1',
            'customer_name' => 'Cust',
            'customer_phone' => '010',
            'shipping_company_id' => $company->id,
            'currency' => 'EGP',
            'status' => GrossSalesInvoice::STATUS_DRAFT,
            'created_by' => $manager->id,
        ]);

        $this->actingAs($manager)
            ->from(route('gross.shipping.index'))
            ->delete(route('gross.shipping.destroy', $company))
            ->assertRedirect(route('gross.shipping.index'))
            ->assertSessionHasErrors('shipping');

        $this->assertDatabaseHas('gross_shipping_companies', ['id' => $company->id]);
    }

    public function test_create_form_suggests_existing_names(): void
    {
        $manager = User::factory()->manager()->create();
        GrossShippingCompany::query()->create([
            'name' => 'Existing Shipper',
            'status' => GrossShippingCompany::STATUS_ACTIVE,
        ]);

        $this->withSession(['locale' => 'en'])->actingAs($manager)
            ->get(route('gross.shipping.create'))
            ->assertOk()
            ->assertSee('gross-suggest-shipping-name', false)
            ->assertSee('Existing Shipper', false);
    }

    public function test_show_page_lists_stats_and_invoices(): void
    {
        $manager = User::factory()->manager()->create();
        $company = GrossShippingCompany::query()->create([
            'name' => 'Stats Courier',
            'status' => GrossShippingCompany::STATUS_ACTIVE,
        ]);

        GrossSalesInvoice::query()->create([
            'invoice_number' => 'INV-SHIP-STATS',
            'customer_name' => 'Cust',
            'customer_phone' => '010',
            'shipping_company_id' => $company->id,
            'shipping_company_name' => $company->name,
            'currency' => 'EGP',
            'shipping_fee' => 30,
            'shipping_fee_status' => GrossSalesInvoice::SHIPPING_FEE_PENDING,
            'status' => GrossSalesInvoice::STATUS_CONFIRMED,
            'created_by' => $manager->id,
        ]);

        $this->withSession(['locale' => 'en'])->actingAs($manager)
            ->get(route('gross.shipping.show', $company))
            ->assertOk()
            ->assertSee('Invoices by status', false)
            ->assertSee('Pieces with this courier', false)
            ->assertSee('Shipping fees', false)
            ->assertSee('INV-SHIP-STATS', false);
    }

    public function test_excel_import_and_template(): void
    {
        $manager = User::factory()->manager()->create();
        GrossShippingCompany::query()->create([
            'name' => 'Bosta',
            'status' => GrossShippingCompany::STATUS_ACTIVE,
        ]);

        $path = storage_path('app/tmp/test-shipping-import.xlsx');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        $sheet = new Spreadsheet;
        $sheet->getActiveSheet()->fromArray([
            ['name', 'phone', 'address', 'notes', 'status'],
            ['New Ship', '01550001111', 'Cairo', 'Note', 'ACTIVE'],
            ['Bosta', '01001112233', '', '', 'ACTIVE'],
        ]);
        (new Xlsx($sheet))->save($path);
        $sheet->disconnectWorksheets();

        $upload = new UploadedFile(
            $path,
            'shipping.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $this->actingAs($manager)
            ->post(route('gross.shipping.import'), ['file' => $upload])
            ->assertRedirect(route('gross.shipping.index'))
            ->assertSessionHas('success');

        $this->assertSame(2, GrossShippingCompany::query()->count());
        $this->assertDatabaseHas('gross_shipping_companies', [
            'name' => 'New Ship',
            'phone' => '01550001111',
        ]);

        $this->actingAs($manager)
            ->get(route('gross.shipping.template'))
            ->assertOk()
            ->assertHeader('content-disposition');
    }
}
