<?php

namespace Tests\Feature\Gross;

use App\Models\Gross\GrossCategory;
use App\Models\Gross\GrossItem;
use App\Models\User;
use App\Services\Gross\CategoryService;
use App\Services\Gross\ItemService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CategoryCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_main_and_sub_category(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)
            ->post(route('gross.categories.store'), [
                'name_ar' => 'ملابس',
                'name_en' => 'Clothing',
            ])
            ->assertRedirect(route('gross.categories.index'));

        $root = GrossCategory::query()->whereNull('parent_id')->firstOrFail();
        $this->assertSame('Clothing', $root->name_en);

        $this->actingAs($manager)
            ->post(route('gross.categories.store'), [
                'name_ar' => 'فساتين',
                'name_en' => 'Dresses',
                'parent_id' => $root->id,
            ])
            ->assertRedirect(route('gross.categories.index'));

        $this->assertDatabaseHas('gross_categories', [
            'name_en' => 'Dresses',
            'parent_id' => $root->id,
        ]);

        $this->withSession(['locale' => 'en'])->actingAs($manager)
            ->get(route('gross.items.create'))
            ->assertOk()
            ->assertSee('Clothing', false)
            ->assertSee('Dresses', false);
    }

    public function test_categories_page_has_search_edit_and_import(): void
    {
        $manager = User::factory()->manager()->create();
        $cats = app(CategoryService::class);
        $root = $cats->create(['name_ar' => 'ملابس', 'name_en' => 'Clothing']);
        $cats->create(['name_ar' => 'فساتين', 'name_en' => 'Dresses', 'parent_id' => $root->id]);

        $this->withSession(['locale' => 'en'])->actingAs($manager)
            ->get(route('gross.categories.index'))
            ->assertOk()
            ->assertSee('Import from Excel', false)
            ->assertSee(route('gross.categories.template'), false)
            ->assertSee('bi-pencil', false);

        $this->actingAs($manager)
            ->get(route('gross.categories.index', ['q' => 'Dress']))
            ->assertOk()
            ->assertSee('Dresses', false)
            ->assertDontSee('>Clothing</td>', false);
    }

    public function test_can_update_category(): void
    {
        $manager = User::factory()->manager()->create();
        $root = app(CategoryService::class)->create([
            'name_ar' => 'قديم',
            'name_en' => 'Old',
        ]);

        $this->actingAs($manager)
            ->put(route('gross.categories.update', $root), [
                'name_ar' => 'جديد',
                'name_en' => 'New',
                'parent_id' => null,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('gross_categories', [
            'id' => $root->id,
            'name_en' => 'New',
            'name_ar' => 'جديد',
        ]);
    }

    public function test_excel_import_creates_main_then_sub(): void
    {
        $manager = User::factory()->manager()->create();
        $service = app(CategoryService::class);
        $path = storage_path('app/tmp/test-cat-import.xlsx');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        $service->writeTemplate($path);

        $upload = new UploadedFile(
            $path,
            'categories.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $this->actingAs($manager)
            ->post(route('gross.categories.import'), ['file' => $upload])
            ->assertRedirect(route('gross.categories.index'))
            ->assertSessionHas('success');

        $clothing = GrossCategory::query()->where('name_en', 'Clothing')->whereNull('parent_id')->firstOrFail();
        $this->assertDatabaseHas('gross_categories', [
            'name_en' => 'Dresses',
            'parent_id' => $clothing->id,
        ]);
    }

    public function test_cannot_delete_category_in_use(): void
    {
        $manager = User::factory()->manager()->create();
        $root = app(CategoryService::class)->create([
            'name_ar' => 'مستخدم',
            'name_en' => 'Used',
        ]);
        app(ItemService::class)->create([
            'name' => 'Item',
            'category_id' => $root->id,
            'reorder_level' => 5,
            'status' => GrossItem::STATUS_ACTIVE,
        ]);

        $this->actingAs($manager)
            ->from(route('gross.categories.index'))
            ->delete(route('gross.categories.destroy', $root))
            ->assertRedirect()
            ->assertSessionHasErrors('category');
    }
}
