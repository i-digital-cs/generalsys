<?php

namespace Tests\Unit\GeneralHouse;

use App\Models\GeneralHouse\GhProduct;
use App\Models\GeneralHouse\GhProductOffering;
use App\Models\GeneralHouse\GhServiceCategory;
use App\Models\GeneralHouse\GhTreatment;
use App\Services\GeneralHouse\Catalog\GhSellableCatalogService;
use App\Support\GeneralHouse\GhCatalogStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GhSellableCatalogServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_offering_or_parents_are_not_sellable(): void
    {
        $category = GhServiceCategory::factory()->create(['status' => GhCatalogStatus::ACTIVE]);
        $product = GhProduct::factory()->create([
            'category_id' => $category->id,
            'status' => GhCatalogStatus::ACTIVE,
        ]);
        $treatment = GhTreatment::factory()->create(['status' => GhCatalogStatus::ACTIVE]);

        $active = GhProductOffering::factory()->create([
            'product_id' => $product->id,
            'treatment_id' => $treatment->id,
            'status' => GhCatalogStatus::ACTIVE,
            'standard_price' => '8.00',
        ]);

        $inactiveOffering = GhProductOffering::factory()->create([
            'product_id' => $product->id,
            'treatment_id' => GhTreatment::factory()->create(['status' => GhCatalogStatus::ACTIVE])->id,
            'status' => GhCatalogStatus::INACTIVE,
            'standard_price' => '9.00',
        ]);

        $service = app(GhSellableCatalogService::class);

        $this->assertTrue($service->isSellable($active->fresh(['product.category', 'treatment'])));
        $this->assertFalse($service->isSellable($inactiveOffering->fresh(['product.category', 'treatment'])));

        $product->update(['status' => GhCatalogStatus::INACTIVE]);
        $this->assertFalse($service->isSellable($active->fresh(['product.category', 'treatment'])));

        $product->update(['status' => GhCatalogStatus::ACTIVE]);
        $category->update(['status' => GhCatalogStatus::INACTIVE]);
        $this->assertFalse($service->isSellable($active->fresh(['product.category', 'treatment'])));

        $ids = $service->activeOfferings()->pluck('id');
        $this->assertFalse($ids->contains($active->id));
        $this->assertFalse($ids->contains($inactiveOffering->id));
    }

    public function test_active_chain_appears_in_sellable_list(): void
    {
        $offering = GhProductOffering::factory()->create([
            'status' => GhCatalogStatus::ACTIVE,
            'standard_price' => '12.00',
        ]);

        $service = app(GhSellableCatalogService::class);
        $this->assertTrue($service->activeOfferings()->contains('id', $offering->id));
    }
}
