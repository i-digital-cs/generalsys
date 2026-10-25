<?php

namespace Database\Factories\GeneralHouse;

use App\Models\GeneralHouse\GhProduct;
use App\Models\GeneralHouse\GhProductOffering;
use App\Models\GeneralHouse\GhTreatment;
use App\Support\GeneralHouse\GhCatalogStatus;
use App\Support\GeneralHouse\GhTaxClass;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GhProductOffering> */
class GhProductOfferingFactory extends Factory
{
    protected $model = GhProductOffering::class;

    public function definition(): array
    {
        return [
            'product_id' => GhProduct::factory(),
            'treatment_id' => GhTreatment::factory(),
            'standard_price' => '10.00',
            'currency' => 'AED',
            'tax_class' => GhTaxClass::STANDARD_RATED,
            'status' => GhCatalogStatus::ACTIVE,
            'sort_order' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => GhCatalogStatus::INACTIVE]);
    }
}
