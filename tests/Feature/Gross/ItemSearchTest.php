<?php

namespace Tests\Feature\Gross;

use App\Models\Gross\GrossItem;
use App\Models\User;
use App\Services\Gross\CategoryService;
use App\Services\Gross\ItemService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_json_returns_matching_active_items(): void
    {
        $manager = User::factory()->manager()->create();
        $cats = app(CategoryService::class);
        $root = $cats->create(['name_ar' => 'ملابس', 'name_en' => 'Clothing']);
        $sub = $cats->create([
            'name_ar' => 'فساتين',
            'name_en' => 'Dresses',
            'parent_id' => $root->id,
        ]);
        $items = app(ItemService::class);
        $match = $items->create([
            'name' => 'Blue Dress Silk',
            'status' => GrossItem::STATUS_ACTIVE,
            'default_sale_price' => 120,
            'subcategory_id' => $sub->id,
        ]);
        $items->create([
            'name' => 'Other Product',
            'status' => GrossItem::STATUS_ACTIVE,
            'default_sale_price' => 50,
        ]);

        $this->withSession(['locale' => 'en'])->actingAs($manager)
            ->getJson(route('gross.items.search', ['q' => 'Blue Dress']))
            ->assertOk()
            ->assertJsonFragment(['id' => $match->id])
            ->assertJsonFragment(['sale_price' => '120.00'])
            ->assertJsonFragment(['category' => 'Clothing', 'subcategory' => 'Dresses'])
            ->assertJsonMissing(['id' => GrossItem::query()->where('name', 'Other Product')->value('id')]);
    }

    public function test_search_requires_authentication(): void
    {
        $this->getJson(route('gross.items.search', ['q' => 'x']))
            ->assertUnauthorized();
    }

    public function test_empty_query_returns_empty_list(): void
    {
        $manager = User::factory()->manager()->create();
        app(ItemService::class)->create([
            'name' => 'Something',
            'status' => GrossItem::STATUS_ACTIVE,
        ]);

        $this->actingAs($manager)
            ->getJson(route('gross.items.search', ['q' => '']))
            ->assertOk()
            ->assertExactJson([]);
    }
}
