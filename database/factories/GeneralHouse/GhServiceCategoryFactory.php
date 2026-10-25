<?php

namespace Database\Factories\GeneralHouse;

use App\Models\GeneralHouse\GhServiceCategory;
use App\Support\GeneralHouse\GhCatalogStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GhServiceCategory> */
class GhServiceCategoryFactory extends Factory
{
    protected $model = GhServiceCategory::class;

    public function definition(): array
    {
        return [
            'name_ar' => 'فئة '.fake()->unique()->numerify('###'),
            'name_en' => 'Category '.fake()->unique()->numerify('###'),
            'description_ar' => null,
            'description_en' => null,
            'status' => GhCatalogStatus::ACTIVE,
            'sort_order' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => GhCatalogStatus::INACTIVE]);
    }
}
