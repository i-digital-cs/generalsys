<?php

namespace Tests\Unit\Gross;

use App\Models\Gross\GrossItem;
use App\Models\Gross\GrossItemAttribute;
use App\Services\Gross\CashierCatalogService;
use App\Services\Gross\CategoryService;
use App\Services\Gross\ItemAttributeService;
use App\Services\Gross\ItemService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashierCatalogServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_groups_siblings_by_name_within_subcategory(): void
    {
        $cats = app(CategoryService::class);
        $root = $cats->create(['name_ar' => 'ملابس', 'name_en' => 'Clothing']);
        $sub = $cats->create([
            'name_ar' => 'فساتين',
            'name_en' => 'Dresses',
            'parent_id' => $root->id,
        ]);
        $attrs = app(ItemAttributeService::class);
        $sizeS = $attrs->create(['kind' => GrossItemAttribute::KIND_SIZE, 'name_ar' => 'صغير', 'name_en' => 'S']);
        $sizeM = $attrs->create(['kind' => GrossItemAttribute::KIND_SIZE, 'name_ar' => 'وسط', 'name_en' => 'M']);
        $items = app(ItemService::class);

        $items->create([
            'name' => 'Summer Dress',
            'subcategory_id' => $sub->id,
            'size_id' => $sizeS->id,
            'default_sale_price' => 100,
            'status' => GrossItem::STATUS_ACTIVE,
            'description' => 'Light summer dress',
        ]);
        $items->create([
            'name' => 'Summer Dress',
            'subcategory_id' => $sub->id,
            'size_id' => $sizeM->id,
            'default_sale_price' => 120,
            'status' => GrossItem::STATUS_ACTIVE,
        ]);
        $items->create([
            'name' => 'Other Dress',
            'subcategory_id' => $sub->id,
            'default_sale_price' => 80,
            'status' => GrossItem::STATUS_ACTIVE,
        ]);
        $items->create([
            'name' => 'Inactive Dress',
            'subcategory_id' => $sub->id,
            'status' => GrossItem::STATUS_INACTIVE,
        ]);

        $catalog = app(CashierCatalogService::class);
        $this->withSession(['locale' => 'en']);
        app()->setLocale('en');
        $groups = $catalog->productGroups($sub->id);

        $this->assertCount(2, $groups);
        $summer = collect($groups)->firstWhere('name', 'Summer Dress');
        $this->assertNotNull($summer);
        $this->assertSame(2, $summer['variant_count']);
        $this->assertSame('100.00', $summer['price_from']);
        $this->assertSame('Light summer dress', $summer['description']);
        $this->assertSame($sub->id, $summer['subcategory_id']);
        $this->assertSame('Dresses', $summer['subcategory']);
        $this->assertSame('Clothing', $summer['category']);

        $variants = $catalog->variants('Summer Dress', $sub->id);
        $this->assertCount(2, $variants);
        $this->assertEqualsCanonicalizing(['S', 'M'], array_column($variants, 'size'));
    }

    public function test_subcategories_only_for_root_parent(): void
    {
        $cats = app(CategoryService::class);
        $root = $cats->create(['name_ar' => 'أ', 'name_en' => 'A']);
        $other = $cats->create(['name_ar' => 'ب', 'name_en' => 'B']);
        $cats->create(['name_ar' => 'فرعي', 'name_en' => 'Child', 'parent_id' => $root->id]);

        $catalog = app(CashierCatalogService::class);
        $this->assertCount(1, $catalog->subcategories($root->id));
        $this->assertSame([], $catalog->subcategories($other->id));
        $this->assertSame([], $catalog->subcategories(0));
    }

    public function test_categories_lists_roots(): void
    {
        $cats = app(CategoryService::class);
        $cats->create(['name_ar' => 'جذر', 'name_en' => 'Root']);
        $cats->create(['name_ar' => 'فرعي', 'name_en' => 'Child', 'parent_id' => $cats->roots()->first()->id]);

        $list = app(CashierCatalogService::class)->categories();
        $this->assertCount(1, $list);
        $this->assertArrayHasKey('id', $list[0]);
        $this->assertArrayHasKey('name', $list[0]);
    }
}
