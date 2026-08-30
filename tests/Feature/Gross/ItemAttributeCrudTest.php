<?php

namespace Tests\Feature\Gross;

use App\Models\Gross\GrossItem;
use App\Models\Gross\GrossItemAttribute;
use App\Models\User;
use App\Services\Gross\ItemAttributeService;
use App\Services\Gross\ItemService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ItemAttributeCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_add_color_and_select_on_item(): void
    {
        $manager = User::factory()->manager()->create();
        $attrs = app(ItemAttributeService::class);

        $color = $attrs->create([
            'kind' => GrossItemAttribute::KIND_COLOR,
            'name_ar' => 'كحلي',
            'name_en' => 'Navy',
        ]);
        $size = $attrs->create([
            'kind' => GrossItemAttribute::KIND_SIZE,
            'name_ar' => 'كبير',
            'name_en' => 'L',
        ]);

        $item = app(ItemService::class)->create([
            'name' => 'Coat',
            'color_id' => $color->id,
            'size_id' => $size->id,
            'reorder_level' => 5,
            'status' => GrossItem::STATUS_ACTIVE,
        ]);

        $this->assertSame($color->id, (int) $item->color_id);
        $this->assertSame('Navy', $item->color);
        $this->assertSame('L', $item->size);

        $this->actingAs($manager)
            ->get(route('gross.items.index', ['color_id' => $color->id]))
            ->assertOk()
            ->assertSee('Coat', false);
    }

    public function test_duplicate_attribute_rejected(): void
    {
        $manager = User::factory()->manager()->create();
        app(ItemAttributeService::class)->create([
            'kind' => GrossItemAttribute::KIND_MODEL,
            'name_ar' => 'م1',
            'name_en' => 'M1',
        ]);

        $this->actingAs($manager)
            ->from(route('gross.attributes.index'))
            ->post(route('gross.attributes.store'), [
                'kind' => 'MODEL',
                'name_ar' => 'م1-ب',
                'name_en' => 'M1',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('name_en');
    }

    public function test_attributes_page_uses_tabs(): void
    {
        $manager = User::factory()->manager()->create();

        $this->withSession(['locale' => 'en'])->actingAs($manager)
            ->get(route('gross.attributes.index'))
            ->assertOk()
            ->assertSee('nav-tabs', false)
            ->assertSee('Import from Excel', false)
            ->assertSee(route('gross.attributes.template'), false)
            ->assertSee('name="q"', false);
    }

    public function test_attributes_search_filters_by_name(): void
    {
        $manager = User::factory()->manager()->create();
        $attrs = app(ItemAttributeService::class);
        $attrs->create([
            'kind' => GrossItemAttribute::KIND_COLOR,
            'name_ar' => 'كحلي',
            'name_en' => 'Navy',
        ]);
        $attrs->create([
            'kind' => GrossItemAttribute::KIND_COLOR,
            'name_ar' => 'أحمر',
            'name_en' => 'Red',
        ]);

        $this->actingAs($manager)
            ->get(route('gross.attributes.index', ['tab' => 'COLOR', 'q' => 'Navy']))
            ->assertOk()
            ->assertSee('Navy', false)
            ->assertDontSee('Red', false);
    }

    public function test_excel_import_creates_and_skips_duplicates(): void
    {
        $manager = User::factory()->manager()->create();
        $service = app(ItemAttributeService::class);
        $service->create([
            'kind' => GrossItemAttribute::KIND_COLOR,
            'name_ar' => 'كحلي',
            'name_en' => 'Navy',
        ]);

        $path = storage_path('app/tmp/test-attr-import.xlsx');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        $service->writeTemplate($path);

        $upload = new UploadedFile(
            $path,
            'attributes.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $this->actingAs($manager)
            ->post(route('gross.attributes.import'), ['file' => $upload])
            ->assertRedirect(route('gross.attributes.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('gross_item_attributes', [
            'kind' => 'SIZE',
            'name_en' => 'L',
        ]);
        $this->assertDatabaseHas('gross_item_attributes', [
            'kind' => 'MODEL',
            'name_en' => 'Classic',
        ]);
        $this->assertSame(1, GrossItemAttribute::query()->where('kind', 'COLOR')->where('name_en', 'Navy')->count());
    }

    public function test_template_download(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)
            ->get(route('gross.attributes.template'))
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_update_renames_and_syncs_item_label(): void
    {
        $manager = User::factory()->manager()->create();
        $attrs = app(ItemAttributeService::class);
        $color = $attrs->create([
            'kind' => GrossItemAttribute::KIND_COLOR,
            'name_ar' => 'كحلي',
            'name_en' => 'Navy',
        ]);

        $item = app(ItemService::class)->create([
            'name' => 'Coat',
            'color_id' => $color->id,
            'reorder_level' => 5,
            'status' => GrossItem::STATUS_ACTIVE,
        ]);
        $this->assertSame('Navy', $item->color);

        $this->actingAs($manager)
            ->put(route('gross.attributes.update', $color), [
                'name_ar' => 'أزرق كحلي',
                'name_en' => 'Dark Navy',
            ])
            ->assertRedirect(route('gross.attributes.index', ['tab' => 'COLOR']));

        $this->assertDatabaseHas('gross_item_attributes', [
            'id' => $color->id,
            'name_en' => 'Dark Navy',
            'name_ar' => 'أزرق كحلي',
        ]);
        $this->assertDatabaseHas('gross_items', [
            'id' => $item->id,
            'color' => 'Dark Navy',
        ]);
    }
}
