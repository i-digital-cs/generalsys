<?php

namespace Database\Factories\GeneralHouse;

use App\Models\GeneralHouse\GhB2bContract;
use App\Models\GeneralHouse\GhCustomer;
use App\Support\GeneralHouse\GhContractStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GhB2bContract> */
class GhB2bContractFactory extends Factory
{
    protected $model = GhB2bContract::class;

    public function definition(): array
    {
        return [
            'customer_id' => GhCustomer::factory()->b2b(),
            'reference' => 'GH-CTR-'.fake()->unique()->numerify('######'),
            'effective_from' => now()->toDateString(),
            'effective_to' => now()->addYear()->toDateString(),
            'status' => GhContractStatus::ACTIVE,
            'notes' => null,
        ];
    }
}
