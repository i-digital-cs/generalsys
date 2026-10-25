<?php

namespace Database\Factories\GeneralHouse;

use App\Models\GeneralHouse\GhTreatment;
use App\Support\GeneralHouse\GhCatalogStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GhTreatment> */
class GhTreatmentFactory extends Factory
{
    protected $model = GhTreatment::class;

    public function definition(): array
    {
        return [
            'name_ar' => 'معالجة '.fake()->unique()->numerify('###'),
            'name_en' => 'Treatment '.fake()->unique()->numerify('###'),
            'status' => GhCatalogStatus::ACTIVE,
            'sort_order' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => GhCatalogStatus::INACTIVE]);
    }
}
