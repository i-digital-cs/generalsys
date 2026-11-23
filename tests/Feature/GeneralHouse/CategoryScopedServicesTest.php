<?php

namespace Tests\Feature\GeneralHouse;

use App\Models\GeneralHouse\GhOrderLine;
use App\Models\GeneralHouse\GhProduct;
use App\Models\GeneralHouse\GhProductOffering;
use App\Models\GeneralHouse\GhServiceCategory;
use App\Models\GeneralHouse\GhTreatment;
use App\Models\User;
use App\Services\GeneralHouse\Catalog\GhCategoryTreatmentService;
use App\Support\GeneralHouse\GhCatalogStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CategoryScopedServicesTest extends TestCase
{
    use RefreshDatabase;

    private function map(GhServiceCategory $category, GhTreatment $treatment): void
    {
        if (! $category->treatments()->where('gh_treatments.id', $treatment->id)->exists()) {
            $category->treatments()->attach($treatment->id, ['sort_order' => 0]);
        }
    }

    public function test_cross_category_offering_is_rejected_on_product_create(): void
    {
        $manager = User::factory()->manager()->create();
        $laundry = GhServiceCategory::factory()->create(['name_en' => 'Laundry']);
        $deep = GhServiceCategory::factory()->create(['name_en' => 'Deep']);
        $wash = GhTreatment::factory()->create(['name_en' => 'Wash']);
        $this->map($deep, $wash);

        $this->actingAs($manager)
            ->post(route('gh.products.store'), [
                'category_id' => $laundry->id,
                'code' => 'XCAT-1',
                'name_ar' => 'أ',
                'name_en' => 'A',
                'status' => GhCatalogStatus::ACTIVE,
                'offerings' => [
                    ['treatment_id' => $wash->id, 'enabled' => '1', 'standard_price' => '8.00'],
                ],
            ])
            ->assertSessionHasErrors('offerings.0.treatment_id');

        $this->assertDatabaseMissing('gh_products', ['code' => 'XCAT-1']);
        $this->assertSame(0, GhProductOffering::query()->count());
    }

    public function test_create_product_with_mapped_services_succeeds(): void
    {
        $manager = User::factory()->manager()->create();
        $category = GhServiceCategory::factory()->create();
        $wash = GhTreatment::factory()->create(['name_en' => 'Wash']);
        $iron = GhTreatment::factory()->create(['name_en' => 'Iron']);
        $this->map($category, $wash);
        $this->map($category, $iron);

        $this->actingAs($manager)
            ->post(route('gh.products.store'), [
                'category_id' => $category->id,
                'code' => 'MAP-OK',
                'name_ar' => 'قميص',
                'name_en' => 'Shirt',
                'status' => GhCatalogStatus::ACTIVE,
                'offerings' => [
                    ['treatment_id' => $wash->id, 'enabled' => '1', 'standard_price' => '8.00'],
                    ['treatment_id' => $iron->id, 'enabled' => '1', 'standard_price' => '5.00'],
                ],
            ])
            ->assertRedirect(route('gh.products.index'));

        $this->assertSame(2, GhProductOffering::query()->where('status', GhCatalogStatus::ACTIVE)->count());
    }

    public function test_product_form_payload_only_includes_mapped_treatments(): void
    {
        $manager = User::factory()->manager()->create();
        $laundry = GhServiceCategory::factory()->create(['name_en' => 'Laundry']);
        $furniture = GhServiceCategory::factory()->create(['name_en' => 'Furniture']);
        $wash = GhTreatment::factory()->create(['name_en' => 'Wash Only Unique']);
        $polish = GhTreatment::factory()->create(['name_en' => 'Polish Unique']);
        $this->map($laundry, $wash);
        $this->map($furniture, $polish);

        $html = $this->actingAs($manager)
            ->get(route('gh.products.create'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Wash Only Unique', $html);
        $this->assertStringContainsString('Polish Unique', $html);
        $this->assertStringContainsString('"'.$laundry->id.'"', $html);
        $this->assertMatchesRegularExpression(
            '/"'.$laundry->id.'"\s*:\s*\[[^\]]*Wash Only Unique/s',
            $html
        );
        $this->assertDoesNotMatchRegularExpression(
            '/"'.$laundry->id.'"\s*:\s*\[[^\]]*Polish Unique/s',
            $html
        );
    }

    public function test_large_catalog_payload_supports_fifty_plus_services(): void
    {
        $manager = User::factory()->manager()->create();
        $category = GhServiceCategory::factory()->create();
        for ($i = 1; $i <= 55; $i++) {
            $treatment = GhTreatment::factory()->create([
                'name_en' => 'Service EN '.$i,
                'name_ar' => 'خدمة '.$i,
                'sort_order' => $i,
            ]);
            $this->map($category, $treatment);
        }

        $html = $this->actingAs($manager)
            ->get(route('gh.products.create'))
            ->assertOk()
            ->assertSee('data-page-size="12"', false)
            ->assertSee('gh-svc-search', false)
            ->getContent();

        $this->assertStringContainsString('Service EN 1', $html);
        $this->assertStringContainsString('Service EN 55', $html);
        $this->assertSame(55, substr_count($html, 'Service EN '));
    }

    public function test_mapping_removal_blocked_when_active_offerings_exist(): void
    {
        $manager = User::factory()->manager()->create();
        $category = GhServiceCategory::factory()->create();
        $keep = GhTreatment::factory()->create(['name_en' => 'Keep']);
        $drop = GhTreatment::factory()->create(['name_en' => 'Drop']);
        $this->map($category, $keep);
        $this->map($category, $drop);
        $product = GhProduct::factory()->create(['category_id' => $category->id]);
        GhProductOffering::factory()->create([
            'product_id' => $product->id,
            'treatment_id' => $drop->id,
            'standard_price' => '9.00',
            'status' => GhCatalogStatus::ACTIVE,
        ]);

        $this->actingAs($manager)
            ->put(route('gh.categories.treatments.sync', $category), [
                'treatment_ids' => [$keep->id],
            ])
            ->assertSessionHasErrors('treatment_ids');

        $this->assertTrue(
            DB::table('gh_category_treatment')
                ->where('category_id', $category->id)
                ->where('treatment_id', $drop->id)
                ->exists()
        );
    }

    public function test_mapping_sync_can_add_and_remove_unused_treatments(): void
    {
        $manager = User::factory()->manager()->create();
        $category = GhServiceCategory::factory()->create();
        $a = GhTreatment::factory()->create(['name_en' => 'A']);
        $b = GhTreatment::factory()->create(['name_en' => 'B']);
        $this->map($category, $a);

        $this->actingAs($manager)
            ->put(route('gh.categories.treatments.sync', $category), [
                'treatment_ids' => [$b->id],
            ])
            ->assertRedirect(route('gh.categories.treatments', $category));

        $this->assertDatabaseMissing('gh_category_treatment', [
            'category_id' => $category->id,
            'treatment_id' => $a->id,
        ]);
        $this->assertDatabaseHas('gh_category_treatment', [
            'category_id' => $category->id,
            'treatment_id' => $b->id,
        ]);
    }

    public function test_category_change_blocked_when_active_offerings_incompatible(): void
    {
        $manager = User::factory()->manager()->create();
        $laundry = GhServiceCategory::factory()->create(['name_en' => 'Laundry']);
        $furniture = GhServiceCategory::factory()->create(['name_en' => 'Furniture']);
        $wash = GhTreatment::factory()->create();
        $this->map($laundry, $wash);
        $product = GhProduct::factory()->create(['category_id' => $laundry->id, 'code' => 'CHG-1']);
        GhProductOffering::factory()->create([
            'product_id' => $product->id,
            'treatment_id' => $wash->id,
            'status' => GhCatalogStatus::ACTIVE,
        ]);

        $this->actingAs($manager)
            ->put(route('gh.products.update', $product), [
                'category_id' => $furniture->id,
                'code' => 'CHG-1',
                'name_ar' => $product->name_ar,
                'name_en' => $product->name_en,
                'status' => GhCatalogStatus::ACTIVE,
                'offerings' => [],
            ])
            ->assertSessionHasErrors('category_id');

        $this->assertDatabaseHas('gh_products', [
            'id' => $product->id,
            'category_id' => $laundry->id,
        ]);
    }

    public function test_empty_category_shows_empty_state_and_manage_link(): void
    {
        $manager = User::factory()->manager()->create();
        $empty = GhServiceCategory::factory()->create(['name_en' => 'Furniture Empty']);

        $this->withSession(['locale' => 'en'])->actingAs($manager)
            ->get(route('gh.products.create'))
            ->assertOk()
            ->assertSee('No services are assigned to this category yet.', false)
            ->assertSee('Manage category services', false)
            ->assertSee('gh-svc-manage-link', false);

        $this->withSession(['locale' => 'en'])->actingAs($manager)
            ->get(route('gh.categories.treatments', $empty))
            ->assertOk()
            ->assertSee('Assign which catalog services may be priced on products in this category.', false);
    }

    public function test_historical_order_line_untouched_when_editing_mapped_offering(): void
    {
        $manager = User::factory()->manager()->create();
        $category = GhServiceCategory::factory()->create();
        $treatment = GhTreatment::factory()->create();
        $this->map($category, $treatment);
        $product = GhProduct::factory()->create(['category_id' => $category->id, 'code' => 'HIST-MAP']);
        $offering = GhProductOffering::factory()->create([
            'product_id' => $product->id,
            'treatment_id' => $treatment->id,
            'standard_price' => '8.00',
        ]);
        $line = GhOrderLine::factory()->create([
            'offering_id' => $offering->id,
            'unit_price_estimate' => '8.00',
        ]);

        $this->actingAs($manager)
            ->put(route('gh.products.update', $product), [
                'category_id' => $category->id,
                'code' => 'HIST-MAP',
                'name_ar' => $product->name_ar,
                'name_en' => $product->name_en,
                'status' => GhCatalogStatus::ACTIVE,
                'offerings' => [
                    ['treatment_id' => $treatment->id, 'enabled' => '1', 'standard_price' => '11.00'],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('gh_product_offerings', [
            'id' => $offering->id,
            'standard_price' => '11.00',
        ]);
        $this->assertDatabaseHas('gh_order_lines', [
            'id' => $line->id,
            'unit_price_estimate' => '8.00',
        ]);
    }

    public function test_detach_service_throws_when_active_offerings_block(): void
    {
        $category = GhServiceCategory::factory()->create();
        $treatment = GhTreatment::factory()->create();
        $this->map($category, $treatment);
        $product = GhProduct::factory()->create(['category_id' => $category->id]);
        GhProductOffering::factory()->create([
            'product_id' => $product->id,
            'treatment_id' => $treatment->id,
            'status' => GhCatalogStatus::ACTIVE,
        ]);

        $this->expectException(ValidationException::class);
        app(GhCategoryTreatmentService::class)->detach($category, $treatment);
    }
}
