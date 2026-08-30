<?php

namespace Tests\Feature\Gross;

use App\Models\Gross\GrossCustomer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class CustomerImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_excel_import_creates_customer_and_skips_duplicate_phone(): void
    {
        $manager = User::factory()->manager()->create();
        GrossCustomer::query()->create([
            'name' => 'Existing',
            'phone' => '01001112233',
            'address' => null,
        ]);

        $path = storage_path('app/tmp/test-customers-import.xlsx');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        $sheet = new Spreadsheet;
        $sheet->getActiveSheet()->fromArray([
            ['name', 'phone', 'address'],
            ['New Client', '01551112233', 'Giza'],
            ['Dup Phone', '01001112233', 'Anywhere'],
        ]);
        (new Xlsx($sheet))->save($path);
        $sheet->disconnectWorksheets();

        $upload = new UploadedFile(
            $path,
            'customers.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $this->actingAs($manager)
            ->post(route('gross.customers.import'), ['file' => $upload])
            ->assertRedirect(route('gross.customers.index'))
            ->assertSessionHas('success');

        $this->assertSame(2, GrossCustomer::query()->count());
        $this->assertDatabaseHas('gross_customers', [
            'name' => 'New Client',
            'phone' => '01551112233',
            'address' => 'Giza',
        ]);
    }

    public function test_template_download(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)
            ->get(route('gross.customers.template'))
            ->assertOk()
            ->assertHeader('content-disposition');
    }
}
