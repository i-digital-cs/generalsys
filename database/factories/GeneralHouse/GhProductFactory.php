<?php

namespace Database\Factories\GeneralHouse;

use App\Models\GeneralHouse\GhProduct;
use App\Models\GeneralHouse\GhServiceCategory;
use App\Support\GeneralHouse\GhCatalogStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GhProduct> */
class GhProductFactory extends Factory
{
    protected $model = GhProduct::class;

    public function definition(): array
    {
        return [
            'category_id' => GhServiceCategory::factory(),
            'code' => strtoupper(fake()->unique()->bothify('GH-??##')),
            'name_ar' => 'منتج '.fake()->unique()->numerify('###'),
            'name_en' => 'Product '.fake()->unique()->numerify('###'),
            'image_path' => null,
            'status' => GhCatalogStatus::ACTIVE,
            'sort_order' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => GhCatalogStatus::INACTIVE]);
    }
}
