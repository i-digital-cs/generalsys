<?php

namespace Tests\Feature\GeneralHouse;

use App\Models\GeneralHouse\GhOrderLine;
use App\Models\GeneralHouse\GhProduct;
use App\Models\GeneralHouse\GhProductOffering;
use App\Models\GeneralHouse\GhServiceCategory;
use App\Models\GeneralHouse\GhTreatment;
use App\Models\User;
use App\Services\GeneralHouse\Catalog\GhProductService;
use App\Support\GeneralHouse\GhCatalogStatus;
use App\Support\GeneralHouse\GhTaxClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CatalogCrudTest extends TestCase
{
    use RefreshDatabase;

    private function mapTreatment(GhServiceCategory $category, GhTreatment $treatment): void
    {
        if (! $category->treatments()->where('gh_treatments.id', $treatment->id)->exists()) {
            $category->treatments()->attach($treatment->id, ['sort_order' => 0]);
        }
    }

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
        $this->mapTreatment($category, $treatment);

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

    public function test_offering_create_defaults_tax_class_when_omitted(): void
    {
        $manager = User::factory()->manager()->create();
        $product = GhProduct::factory()->create();
        $treatment = GhTreatment::factory()->create();
        $this->mapTreatment($product->category, $treatment);

        $this->actingAs($manager)
            ->post(route('gh.offerings.store'), [
                'product_id' => $product->id,
                'treatment_id' => $treatment->id,
                'standard_price' => '7.50',
                'status' => GhCatalogStatus::ACTIVE,
                'sort_order' => 0,
            ])
            ->assertRedirect(route('gh.offerings.index'));

        $this->assertDatabaseHas('gh_product_offerings', [
            'product_id' => $product->id,
            'treatment_id' => $treatment->id,
            'tax_class' => GhTaxClass::STANDARD_RATED,
        ]);

        $html = $this->actingAs($manager)->get(route('gh.offerings.index'))->assertOk()->getContent();
        $this->assertStringContainsString(__('gh.offerings.tax_default_hint'), $html);
        $this->assertStringContainsString(__('gh.offerings.advanced_tax'), $html);
    }

    public function test_duplicate_offering_is_rejected(): void
    {
        $manager = User::factory()->manager()->create();
        $product = GhProduct::factory()->create();
        $treatment = GhTreatment::factory()->create();
        $this->mapTreatment($product->category, $treatment);
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
        $this->mapTreatment($product->category, $treatment);

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
        $category = GhServiceCategory::factory()->create();

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

        $this->actingAs($employee)
            ->get(route('gh.categories.treatments', $category))
            ->assertForbidden();

        $this->actingAs($employee)
            ->put(route('gh.categories.treatments.sync', $category), [
                'treatment_ids' => [],
            ])
            ->assertForbidden();

        $this->actingAs($employee)
            ->get(route('gh.products.create'))
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

    public function test_create_product_with_one_service_offering(): void
    {
        $manager = User::factory()->manager()->create();
        $category = GhServiceCategory::factory()->create();
        $wash = GhTreatment::factory()->create(['name_en' => 'Wash only', 'name_ar' => 'غسيل فقط']);
        $this->mapTreatment($category, $wash);

        $this->actingAs($manager)
            ->post(route('gh.products.store'), [
                'category_id' => $category->id,
                'code' => 'SHIRT-1',
                'name_ar' => 'قميص',
                'name_en' => 'Shirt',
                'status' => GhCatalogStatus::ACTIVE,
                'sort_order' => 0,
                'offerings' => [
                    [
                        'treatment_id' => $wash->id,
                        'enabled' => '1',
                        'standard_price' => '8.00',
                    ],
                ],
            ])
            ->assertRedirect(route('gh.products.index'));

        $product = GhProduct::query()->where('code', 'SHIRT-1')->firstOrFail();
        $this->assertDatabaseHas('gh_product_offerings', [
            'product_id' => $product->id,
            'treatment_id' => $wash->id,
            'standard_price' => '8.00',
            'currency' => 'AED',
            'status' => GhCatalogStatus::ACTIVE,
            'tax_class' => GhTaxClass::STANDARD_RATED,
        ]);
        $this->assertSame(1, GhProductOffering::query()->where('product_id', $product->id)->count());
    }

    public function test_create_product_with_multiple_service_offerings(): void
    {
        $manager = User::factory()->manager()->create();
        $category = GhServiceCategory::factory()->create();
        $wash = GhTreatment::factory()->create(['name_en' => 'Wash only', 'sort_order' => 1]);
        $iron = GhTreatment::factory()->create(['name_en' => 'Iron only', 'sort_order' => 2]);
        $both = GhTreatment::factory()->create(['name_en' => 'Wash + Iron', 'sort_order' => 3]);
        $this->mapTreatment($category, $wash);
        $this->mapTreatment($category, $iron);
        $this->mapTreatment($category, $both);

        $this->actingAs($manager)
            ->post(route('gh.products.store'), [
                'category_id' => $category->id,
                'code' => 'SHIRT-MULTI',
                'name_ar' => 'قميص',
                'name_en' => 'Shirt',
                'status' => GhCatalogStatus::ACTIVE,
                'offerings' => [
                    ['treatment_id' => $wash->id, 'enabled' => '1', 'standard_price' => '8.00'],
                    ['treatment_id' => $iron->id, 'enabled' => '1', 'standard_price' => '5.00'],
                    ['treatment_id' => $both->id, 'enabled' => '1', 'standard_price' => '12.00'],
                ],
            ])
            ->assertRedirect(route('gh.products.index'));

        $product = GhProduct::query()->where('code', 'SHIRT-MULTI')->firstOrFail();
        $this->assertSame(3, GhProductOffering::query()->where('product_id', $product->id)->where('status', GhCatalogStatus::ACTIVE)->count());
        $this->assertDatabaseHas('gh_product_offerings', [
            'product_id' => $product->id,
            'treatment_id' => $iron->id,
            'standard_price' => '5.00',
        ]);
        $this->assertDatabaseHas('gh_product_offerings', [
            'product_id' => $product->id,
            'treatment_id' => $both->id,
            'standard_price' => '12.00',
        ]);
    }

    public function test_edit_product_updates_prices_and_soft_disables_service(): void
    {
        $manager = User::factory()->manager()->create();
        $category = GhServiceCategory::factory()->create();
        $product = GhProduct::factory()->create([
            'category_id' => $category->id,
            'code' => 'SHIRT-ED',
            'name_en' => 'Shirt',
            'name_ar' => 'قميص',
        ]);
        $wash = GhTreatment::factory()->create(['name_en' => 'Wash only']);
        $iron = GhTreatment::factory()->create(['name_en' => 'Iron only']);
        $both = GhTreatment::factory()->create(['name_en' => 'Wash + Iron']);
        $this->mapTreatment($category, $wash);
        $this->mapTreatment($category, $iron);
        $this->mapTreatment($category, $both);

        $washOffering = GhProductOffering::factory()->create([
            'product_id' => $product->id,
            'treatment_id' => $wash->id,
            'standard_price' => '8.00',
        ]);
        $ironOffering = GhProductOffering::factory()->create([
            'product_id' => $product->id,
            'treatment_id' => $iron->id,
            'standard_price' => '5.00',
        ]);

        $this->actingAs($manager)
            ->put(route('gh.products.update', $product), [
                'category_id' => $category->id,
                'code' => 'SHIRT-ED',
                'name_ar' => 'قميص',
                'name_en' => 'Shirt',
                'status' => GhCatalogStatus::ACTIVE,
                'offerings' => [
                    ['treatment_id' => $wash->id, 'enabled' => '1', 'standard_price' => '9.50'],
                    ['treatment_id' => $iron->id, 'enabled' => '0', 'standard_price' => '5.00'],
                    ['treatment_id' => $both->id, 'enabled' => '1', 'standard_price' => '12.00'],
                ],
            ])
            ->assertRedirect(route('gh.products.index'));

        $this->assertDatabaseHas('gh_product_offerings', [
            'id' => $washOffering->id,
            'standard_price' => '9.50',
            'status' => GhCatalogStatus::ACTIVE,
        ]);
        $this->assertDatabaseHas('gh_product_offerings', [
            'id' => $ironOffering->id,
            'status' => GhCatalogStatus::INACTIVE,
            'standard_price' => '5.00',
        ]);
        $this->assertDatabaseHas('gh_product_offerings', [
            'product_id' => $product->id,
            'treatment_id' => $both->id,
            'standard_price' => '12.00',
            'status' => GhCatalogStatus::ACTIVE,
        ]);
        $this->assertSame(3, GhProductOffering::query()->where('product_id', $product->id)->count());
    }

    public function test_product_form_rejects_invalid_service_id_and_price(): void
    {
        $manager = User::factory()->manager()->create();
        $category = GhServiceCategory::factory()->create();
        $wash = GhTreatment::factory()->create();
        $this->mapTreatment($category, $wash);

        $this->actingAs($manager)
            ->post(route('gh.products.store'), [
                'category_id' => $category->id,
                'code' => 'BAD-SVC',
                'name_ar' => 'أ',
                'name_en' => 'A',
                'status' => GhCatalogStatus::ACTIVE,
                'offerings' => [
                    ['treatment_id' => 999999, 'enabled' => '1', 'standard_price' => '8.00'],
                ],
            ])
            ->assertSessionHasErrors('offerings.0.treatment_id');

        $this->assertDatabaseMissing('gh_products', ['code' => 'BAD-SVC']);

        $this->actingAs($manager)
            ->post(route('gh.products.store'), [
                'category_id' => $category->id,
                'code' => 'BAD-PRICE',
                'name_ar' => 'أ',
                'name_en' => 'A',
                'status' => GhCatalogStatus::ACTIVE,
                'offerings' => [
                    ['treatment_id' => $wash->id, 'enabled' => '1', 'standard_price' => '-2.00'],
                ],
            ])
            ->assertSessionHasErrors('offerings.0.standard_price');

        $this->assertDatabaseMissing('gh_products', ['code' => 'BAD-PRICE']);

        $this->actingAs($manager)
            ->post(route('gh.products.store'), [
                'category_id' => $category->id,
                'code' => 'NO-PRICE',
                'name_ar' => 'أ',
                'name_en' => 'A',
                'status' => GhCatalogStatus::ACTIVE,
                'offerings' => [
                    ['treatment_id' => $wash->id, 'enabled' => '1', 'standard_price' => ''],
                ],
            ])
            ->assertSessionHasErrors('offerings.0.standard_price');
    }

    public function test_product_offering_sync_rolls_back_atomically_on_invalid_price(): void
    {
        $category = GhServiceCategory::factory()->create();
        $good = GhTreatment::factory()->create();
        $bad = GhTreatment::factory()->create();
        $this->mapTreatment($category, $good);
        $this->mapTreatment($category, $bad);

        $thrown = false;
        try {
            app(GhProductService::class)->create([
                'category_id' => $category->id,
                'code' => 'ATOMIC-1',
                'name_ar' => 'قميص',
                'name_en' => 'Shirt',
                'status' => GhCatalogStatus::ACTIVE,
                'offerings' => [
                    ['treatment_id' => $good->id, 'enabled' => true, 'standard_price' => '8.00'],
                    ['treatment_id' => $bad->id, 'enabled' => true, 'standard_price' => '-1'],
                ],
            ]);
        } catch (ValidationException) {
            $thrown = true;
        }

        $this->assertTrue($thrown);
        $this->assertDatabaseMissing('gh_products', ['code' => 'ATOMIC-1']);
        $this->assertSame(0, GhProductOffering::query()->count());
    }

    public function test_disabling_service_preserves_historical_order_line_price(): void
    {
        $manager = User::factory()->manager()->create();
        $product = GhProduct::factory()->create(['code' => 'HIST-1']);
        $treatment = GhTreatment::factory()->create();
        $this->mapTreatment($product->category, $treatment);
        $offering = GhProductOffering::factory()->create([
            'product_id' => $product->id,
            'treatment_id' => $treatment->id,
            'standard_price' => '8.00',
        ]);
        $line = GhOrderLine::factory()->create([
            'offering_id' => $offering->id,
            'unit_price_estimate' => '8.00',
            'product_name_en' => 'Shirt',
            'treatment_name_en' => 'Wash only',
        ]);

        $this->actingAs($manager)
            ->put(route('gh.products.update', $product), [
                'category_id' => $product->category_id,
                'code' => 'HIST-1',
                'name_ar' => $product->name_ar,
                'name_en' => $product->name_en,
                'status' => GhCatalogStatus::ACTIVE,
                'offerings' => [
                    ['treatment_id' => $treatment->id, 'enabled' => '0', 'standard_price' => '99.00'],
                ],
            ])
            ->assertRedirect(route('gh.products.index'));

        $this->assertDatabaseHas('gh_product_offerings', [
            'id' => $offering->id,
            'status' => GhCatalogStatus::INACTIVE,
        ]);
        $this->assertDatabaseHas('gh_order_lines', [
            'id' => $line->id,
            'offering_id' => $offering->id,
            'unit_price_estimate' => '8.00',
        ]);
        $this->assertSame(1, GhProductOffering::query()->whereKey($offering->id)->count());
    }

    public function test_product_create_and_edit_forms_render_service_cards_ar_en(): void
    {
        $manager = User::factory()->manager()->create();
        $category = GhServiceCategory::factory()->create();
        $treatment = GhTreatment::factory()->create([
            'name_en' => 'Wash only',
            'name_ar' => 'غسيل فقط',
        ]);
        $this->mapTreatment($category, $treatment);
        $product = GhProduct::factory()->create(['category_id' => $category->id]);
        GhProductOffering::factory()->create([
            'product_id' => $product->id,
            'treatment_id' => $treatment->id,
            'standard_price' => '8.00',
        ]);

        $this->withSession(['locale' => 'en'])->actingAs($manager)
            ->get(route('gh.products.create'))
            ->assertOk()
            ->assertSee('Available services and prices', false)
            ->assertSee('Wash only', false)
            ->assertSee('gh-svc-grid', false)
            ->assertSee('Show more', false);

        $edit = $this->withSession(['locale' => 'ar'])->actingAs($manager)
            ->get(route('gh.products.edit', $product))
            ->assertOk()
            ->assertSee('الخدمات والأسعار المتاحة', false)
            ->assertSee('عرض المزيد', false)
            ->getContent();
        $this->assertTrue(
            str_contains($edit, 'غسيل فقط') || str_contains($edit, '\u063a\u0633\u064a\u0644 \u0641\u0642\u0637'),
            'Edit form catalog payload should include Arabic treatment name'
        );
        $this->assertStringContainsString('Wash only', $edit);
    }
}
