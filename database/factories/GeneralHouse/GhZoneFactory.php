<?php

namespace Database\Factories\GeneralHouse;

use App\Models\GeneralHouse\GhZone;
use App\Support\GeneralHouse\GhCatalogStatus;
use App\Support\GeneralHouse\GhMinOrderPolicy;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GhZone> */
class GhZoneFactory extends Factory
{
    protected $model = GhZone::class;

    public function definition(): array
    {
        return [
            'name_ar' => 'منطقة '.fake()->unique()->numerify('###'),
            'name_en' => 'Zone '.fake()->unique()->numerify('###'),
            'min_order_amount' => '30.00',
            'min_order_policy' => GhMinOrderPolicy::SURCHARGE,
            'status' => GhCatalogStatus::ACTIVE,
            'sort_order' => 0,
        ];
    }
}
