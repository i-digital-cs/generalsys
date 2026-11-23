<?php

namespace Tests\Feature\GeneralHouse;

use App\Models\GeneralHouse\GhServiceCategory;
use App\Models\GeneralHouse\GhTreatment;
use App\Models\User;
use App\Support\GeneralHouse\GhCatalogStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Backend contract that mirrors the product form JS writeHiddenInputs() behavior:
 * only treatments for the selected category may be posted; search/show-more are display-only.
 */
class ProductServiceCardsClientContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_submit_only_selected_category_services_and_preserve_prices_across_full_category_list(): void
    {
        $manager = User::factory()->manager()->create();
        $laundry = GhServiceCategory::factory()->create(['name_en' => 'Laundry']);
        $furniture = GhServiceCategory::factory()->create(['name_en' => 'Furniture']);

        $wash = GhTreatment::factory()->create(['name_en' => 'Wash']);
        $iron = GhTreatment::factory()->create(['name_en' => 'Iron']);
        $polish = GhTreatment::factory()->create(['name_en' => 'Polish']);

        $laundry->treatments()->attach([$wash->id => ['sort_order' => 0], $iron->id => ['sort_order' => 1]]);
        $furniture->treatments()->attach([$polish->id => ['sort_order' => 0]]);

        // Simulates form submit after search/show-more: full selected-category list is posted
        // (not only the visible filtered page), with selection/price state preserved.
        $this->actingAs($manager)
            ->post(route('gh.products.store'), [
                'category_id' => $laundry->id,
                'code' => 'CARD-1',
                'name_ar' => 'قميص',
                'name_en' => 'Shirt',
                'status' => GhCatalogStatus::ACTIVE,
                'offerings' => [
                    ['treatment_id' => $wash->id, 'enabled' => '1', 'standard_price' => '8.50'],
                    ['treatment_id' => $iron->id, 'enabled' => '0', 'standard_price' => '5.00'],
                ],
            ])
            ->assertRedirect(route('gh.products.index'));

        $this->assertDatabaseHas('gh_product_offerings', [
            'treatment_id' => $wash->id,
            'standard_price' => '8.50',
            'status' => GhCatalogStatus::ACTIVE,
        ]);
        $this->assertDatabaseMissing('gh_product_offerings', [
            'treatment_id' => $iron->id,
            'status' => GhCatalogStatus::ACTIVE,
        ]);
        $this->assertDatabaseMissing('gh_product_offerings', [
            'treatment_id' => $polish->id,
        ]);
    }

    public function test_hidden_other_category_service_ids_are_rejected(): void
    {
        $manager = User::factory()->manager()->create();
        $laundry = GhServiceCategory::factory()->create();
        $furniture = GhServiceCategory::factory()->create();
        $wash = GhTreatment::factory()->create();
        $polish = GhTreatment::factory()->create();
        $laundry->treatments()->attach($wash->id);
        $furniture->treatments()->attach($polish->id);

        $this->actingAs($manager)
            ->post(route('gh.products.store'), [
                'category_id' => $laundry->id,
                'code' => 'CARD-X',
                'name_ar' => 'أ',
                'name_en' => 'A',
                'status' => GhCatalogStatus::ACTIVE,
                'offerings' => [
                    ['treatment_id' => $wash->id, 'enabled' => '1', 'standard_price' => '8.00'],
                    // Would only appear if JS incorrectly submitted another category's card state
                    ['treatment_id' => $polish->id, 'enabled' => '1', 'standard_price' => '20.00'],
                ],
            ])
            ->assertSessionHasErrors('offerings.1.treatment_id');

        $this->assertDatabaseMissing('gh_products', ['code' => 'CARD-X']);
    }
}
