<?php

namespace Tests\Feature\Gross;

use App\Models\Gross\GrossItem;
use App\Models\Gross\GrossItemAttribute;
use App\Models\User;
use App\Services\Gross\CategoryService;
use App\Services\Gross\ItemAttributeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ItemImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_excel_import_creates_item_from_existing_lookups(): void
    {
        $manager = User::factory()->manager()->create();
        $cats = app(CategoryService::class);
        $root = $cats->create(['name_ar' => 'ملابس', 'name_en' => 'Clothing']);
        $cats->create(['name_ar' => 'فساتين', 'name_en' => 'Dresses', 'parent_id' => $root->id]);
        app(ItemAttributeService::class)->create([
            'kind' => GrossItemAttribute::KIND_COLOR,
            'name_ar' => 'كحلي',
            'name_en' => 'Navy',
        ]);

        $path = storage_path('app/tmp/test-items-import.xlsx');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        $sheet = new Spreadsheet;
        $sheet->getActiveSheet()->fromArray([
            ['name', 'color_en', 'size_en', 'model_en', 'category_en', 'subcategory_en', 'reorder_level', 'default_sale_price', 'status', 'description'],
            ['Imported Dress', 'Navy', '', '', 'Clothing', 'Dresses', 5, 99.5, 'ACTIVE', 'From excel'],
        ]);
        (new Xlsx($sheet))->save($path);
        $sheet->disconnectWorksheets();

        $upload = new UploadedFile(
            $path,
            'items.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $this->actingAs($manager)
            ->post(route('gross.items.import'), ['file' => $upload])
            ->assertRedirect(route('gross.items.index'))
            ->assertSessionHas('success');

        $item = GrossItem::query()->where('name', 'Imported Dress')->firstOrFail();
        $this->assertSame('Navy', $item->color);
        $this->assertSame($root->id, (int) $item->category_id);
        $this->assertSame('99.50', (string) $item->default_sale_price);
    }

    public function test_template_download(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)
            ->get(route('gross.items.template'))
            ->assertOk()
            ->assertHeader('content-disposition');
    }
}
