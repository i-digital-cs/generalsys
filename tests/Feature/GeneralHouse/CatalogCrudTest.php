<?php

namespace Tests\Feature\GeneralHouse;

use App\Models\GeneralHouse\GhProduct;
use App\Models\GeneralHouse\GhProductOffering;
use App\Models\GeneralHouse\GhServiceCategory;
use App\Models\GeneralHouse\GhTreatment;
use App\Models\User;
use App\Support\GeneralHouse\GhCatalogStatus;
use App\Support\GeneralHouse\GhTaxClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_crud_category_with_ar_en_fields(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)
            ->post(route('gh.categories.store'), [
                'name_ar' => 'كندورة',
                'name_en' => 'Kandura',
                'status' => GhCatalogStatus::ACTIVE,
                'sort_order' => 1,
            ])
            ->assertRedirect(route('gh.categories.index'));

        $this->assertDatabaseHas('gh_service_categories', [
            'name_ar' => 'كندورة',
            'name_en' => 'Kandura',
            'status' => GhCatalogStatus::ACTIVE,
        ]);

        $category = GhServiceCategory::query()->firstOrFail();

        $this->actingAs($manager)
            ->put(route('gh.categories.update', $category), [
                'name_ar' => 'كندورة صيفية',
                'name_en' => 'Summer Kandura',
                'status' => GhCatalogStatus::INACTIVE,
                'sort_order' => 2,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('gh_service_categories', [
            'id' => $category->id,
            'name_en' => 'Summer Kandura',
            'status' => GhCatalogStatus::INACTIVE,
        ]);
    }

    public function test_manager_can_crud_treatment_product_and_offering(): void
    {
        $manager = User::factory()->manager()->create();
        $category = GhServiceCategory::factory()->create([
            'name_ar' => 'عبايات',
            'name_en' => 'Abayas',
        ]);

        $this->actingAs($manager)
            ->post(route('gh.treatments.store'), [
                'name_ar' => 'كي فقط',
                'name_en' => 'Iron Only',
                'status' => GhCatalogStatus::ACTIVE,
                'sort_order' => 0,
            ])
            ->assertRedirect(route('gh.treatments.index'));

        $treatment = GhTreatment::query()->where('name_en', 'Iron Only')->firstOrFail();

        $this->actingAs($manager)
            ->post(route('gh.products.store'), [
                'category_id' => $category->id,
                'code' => 'SUM-KAND',
                'name_ar' => 'كندورة صيفية',
                'name_en' => 'Summer Kandura',
                'status' => GhCatalogStatus::ACTIVE,
                'sort_order' => 0,
            ])
            ->assertRedirect(route('gh.products.index'));

        $product = GhProduct::query()->where('code', 'SUM-KAND')->firstOrFail();

        $this->actingAs($manager)
            ->post(route('gh.offerings.store'), [
                'product_id' => $product->id,
                'treatment_id' => $treatment->id,
                'standard_price' => '6.00',
                'tax_class' => GhTaxClass::STANDARD_RATED,
                'status' => GhCatalogStatus::ACTIVE,
                'sort_order' => 0,
            ])
            ->assertRedirect(route('gh.offerings.index'));

        $this->assertDatabaseHas('gh_product_offerings', [
            'product_id' => $product->id,
            'treatment_id' => $treatment->id,
            'standard_price' => '6.00',
            'currency' => 'AED',
            'tax_class' => GhTaxClass::STANDARD_RATED,
        ]);
    }

    public function test_duplicate_offering_is_rejected(): void
    {
        $manager = User::factory()->manager()->create();
        $product = GhProduct::factory()->create();
        $treatment = GhTreatment::factory()->create();
        GhProductOffering::factory()->create([
            'product_id' => $product->id,
            'treatment_id' => $treatment->id,
            'standard_price' => '6.00',
        ]);

        $this->actingAs($manager)
            ->post(route('gh.offerings.store'), [
                'product_id' => $product->id,
                'treatment_id' => $treatment->id,
                'standard_price' => '8.00',
                'tax_class' => GhTaxClass::STANDARD_RATED,
                'status' => GhCatalogStatus::ACTIVE,
            ])
            ->assertSessionHasErrors('treatment_id');

        $this->assertSame(1, GhProductOffering::query()->count());
    }

    public function test_negative_price_and_invalid_tax_class_rejected(): void
    {
        $manager = User::factory()->manager()->create();
        $product = GhProduct::factory()->create();
        $treatment = GhTreatment::factory()->create();

        $this->actingAs($manager)
            ->post(route('gh.offerings.store'), [
                'product_id' => $product->id,
                'treatment_id' => $treatment->id,
                'standard_price' => '-1',
                'tax_class' => GhTaxClass::STANDARD_RATED,
                'status' => GhCatalogStatus::ACTIVE,
            ])
            ->assertSessionHasErrors('standard_price');

        $this->actingAs($manager)
            ->post(route('gh.offerings.store'), [
                'product_id' => $product->id,
                'treatment_id' => $treatment->id,
                'standard_price' => '5.00',
                'tax_class' => 'FREE_TOGGLE',
                'status' => GhCatalogStatus::ACTIVE,
            ])
            ->assertSessionHasErrors('tax_class');
    }

    public function test_employee_cannot_manage_catalog(): void
    {
        $employee = User::factory()->employee()->create();

        $this->actingAs($employee)
            ->get(route('gh.categories.index'))
            ->assertForbidden();

        $this->actingAs($employee)
            ->post(route('gh.categories.store'), [
                'name_ar' => 'اختبار',
                'name_en' => 'Test',
                'status' => GhCatalogStatus::ACTIVE,
            ])
            ->assertForbidden();
    }

    public function test_catalog_pages_render_ar_and_en(): void
    {
        $manager = User::factory()->manager()->create();
        GhServiceCategory::factory()->create(['name_en' => 'Uniforms', 'name_ar' => 'زي']);

        $this->withSession(['locale' => 'en'])->actingAs($manager)
            ->get(route('gh.categories.index'))
            ->assertOk()
            ->assertSee('Service categories', false)
            ->assertSee('Uniforms', false);

        $this->withSession(['locale' => 'ar'])->actingAs($manager)
            ->get(route('gh.categories.index'))
            ->assertOk()
            ->assertSee('فئات الخدمة', false)
            ->assertSee('زي', false);
    }

    public function test_cannot_delete_category_with_products(): void
    {
        $manager = User::factory()->manager()->create();
        $category = GhServiceCategory::factory()->create();
        GhProduct::factory()->create(['category_id' => $category->id]);

        $this->actingAs($manager)
            ->from(route('gh.categories.index'))
            ->delete(route('gh.categories.destroy', $category))
            ->assertRedirect()
            ->assertSessionHasErrors('category');

        $this->assertDatabaseHas('gh_service_categories', ['id' => $category->id]);
    }

    public function test_product_code_must_be_unique(): void
    {
        $manager = User::factory()->manager()->create();
        $category = GhServiceCategory::factory()->create();
        GhProduct::factory()->create(['code' => 'DUP-01', 'category_id' => $category->id]);

        $this->actingAs($manager)
            ->post(route('gh.products.store'), [
                'category_id' => $category->id,
                'code' => 'DUP-01',
                'name_ar' => 'أ',
                'name_en' => 'A',
                'status' => GhCatalogStatus::ACTIVE,
            ])
            ->assertSessionHasErrors('code');
    }
}
