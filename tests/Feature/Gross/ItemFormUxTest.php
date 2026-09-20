<?php

namespace Tests\Feature\Gross;

use App\Models\Gross\GrossItem;
use App\Models\User;
use App\Services\Gross\CategoryService;
use App\Services\Gross\ItemService;
use App\Services\Gross\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemFormUxTest extends TestCase
{
    use RefreshDatabase;

    public function test_subcategory_sets_parent_category_automatically(): void
    {
        $manager = User::factory()->manager()->create();
        $cats = app(CategoryService::class);
        $root = $cats->create(['name_ar' => 'ملابس', 'name_en' => 'Clothing']);
        $sub = $cats->create([
            'name_ar' => 'فساتين',
            'name_en' => 'Dresses',
            'parent_id' => $root->id,
        ]);

        $item = app(ItemService::class)->create([
            'name' => 'Summer Dress',
            'subcategory_id' => $sub->id,
            'category_id' => null,
            'reorder_level' => 5,
            'status' => GrossItem::STATUS_ACTIVE,
        ]);

        $this->assertSame($root->id, (int) $item->category_id);
        $this->assertSame($sub->id, (int) $item->subcategory_id);
    }

    public function test_items_index_has_compact_filters_and_low_stock(): void
    {
        $manager = User::factory()->manager()->create();
        $items = app(ItemService::class);
        $low = $items->create(['name' => 'LowItem', 'reorder_level' => 5, 'status' => GrossItem::STATUS_ACTIVE]);
        $ok = $items->create(['name' => 'OkItem', 'reorder_level' => 5, 'status' => GrossItem::STATUS_ACTIVE]);
        app(StockService::class)->adjustIn($ok, 20, 'PIECE', $manager);

        $this->withSession(['locale' => 'en'])->actingAs($manager)
            ->get(route('gross.items.index'))
            ->assertOk()
            ->assertSee('form-control-sm', false)
            ->assertSee('name="low_stock"', false);

        $response = $this->actingAs($manager)
            ->get(route('gross.items.index', ['low_stock' => 1]))
            ->assertOk();
        $names = $response->viewData('items')->pluck('name')->all();
        $this->assertContains('LowItem', $names);
        $this->assertNotContains('OkItem', $names);
        $this->assertSame($low->id, $response->viewData('items')->first()->id);
    }

    public function test_store_form_suggests_existing_items_with_search(): void
    {
        $manager = User::factory()->manager()->create();
        app(ItemService::class)->create([
            'name' => 'Blue Jacket',
            'reorder_level' => 5,
            'status' => GrossItem::STATUS_ACTIVE,
        ]);

        $this->withSession(['locale' => 'en'])->actingAs($manager)
            ->get(route('gross.items.create'))
            ->assertOk()
            ->assertSee('data-item-name-picker', false)
            ->assertSee(route('gross.items.search', [], false), false)
            ->assertSee(__('gross.fields.name_suggest_with_image'), false)
            ->assertDontSee('list="gross-suggest-name"', false);
    }

    public function test_posting_subcategory_without_category_still_links_parent(): void
    {
        $manager = User::factory()->manager()->create();
        $cats = app(CategoryService::class);
        $root = $cats->create(['name_ar' => 'أحذية', 'name_en' => 'Shoes']);
        $sub = $cats->create([
            'name_ar' => 'رياضي',
            'name_en' => 'Sport',
            'parent_id' => $root->id,
        ]);

        $this->actingAs($manager)
            ->post(route('gross.items.store'), [
                'name' => 'Runner',
                'subcategory_id' => $sub->id,
                'reorder_level' => 3,
                'status' => 'ACTIVE',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('gross_items', [
            'name' => 'Runner',
            'subcategory_id' => $sub->id,
            'category_id' => $root->id,
        ]);
    }
}
