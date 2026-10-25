<?php

namespace Database\Factories\GeneralHouse;

use App\Models\GeneralHouse\GhB2bContract;
use App\Models\GeneralHouse\GhB2bContractOffering;
use App\Models\GeneralHouse\GhProductOffering;
use App\Support\GeneralHouse\GhCatalogStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GhB2bContractOffering> */
class GhB2bContractOfferingFactory extends Factory
{
    protected $model = GhB2bContractOffering::class;

    public function definition(): array
    {
        return [
            'contract_id' => GhB2bContract::factory(),
            'offering_id' => GhProductOffering::factory(),
            'contract_price' => '5.00',
            'currency' => 'AED',
            'status' => GhCatalogStatus::ACTIVE,
        ];
    }
}
