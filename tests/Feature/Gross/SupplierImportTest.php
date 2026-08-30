<?php

namespace Tests\Feature\Gross;

use App\Models\Gross\GrossSupplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class SupplierImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_excel_import_creates_supplier_and_skips_duplicate_phone(): void
    {
        $manager = User::factory()->manager()->create();
        GrossSupplier::query()->create([
            'name' => 'Existing',
            'phone' => '01001112233',
            'address' => null,
        ]);

        $path = storage_path('app/tmp/test-suppliers-import.xlsx');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        $sheet = new Spreadsheet;
        $sheet->getActiveSheet()->fromArray([
            ['name', 'phone', 'address', 'notes'],
            ['New Supplier', '01551112233', 'Giza', 'VIP'],
            ['Dup Phone', '01001112233', 'Anywhere', ''],
        ]);
        (new Xlsx($sheet))->save($path);
        $sheet->disconnectWorksheets();

        $upload = new UploadedFile(
            $path,
            'suppliers.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $this->actingAs($manager)
            ->post(route('gross.suppliers.import'), ['file' => $upload])
            ->assertRedirect(route('gross.suppliers.index'))
            ->assertSessionHas('success');

        $this->assertSame(2, GrossSupplier::query()->count());
        $this->assertDatabaseHas('gross_suppliers', [
            'name' => 'New Supplier',
            'phone' => '01551112233',
            'address' => 'Giza',
            'notes' => 'VIP',
        ]);
    }

    public function test_template_download(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)
            ->get(route('gross.suppliers.template'))
            ->assertOk()
            ->assertHeader('content-disposition');
    }
}
