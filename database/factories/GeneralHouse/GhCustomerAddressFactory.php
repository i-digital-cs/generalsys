<?php

namespace Database\Factories\GeneralHouse;

use App\Models\GeneralHouse\GhCustomer;
use App\Models\GeneralHouse\GhCustomerAddress;
use App\Models\GeneralHouse\GhZone;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GhCustomerAddress> */
class GhCustomerAddressFactory extends Factory
{
    protected $model = GhCustomerAddress::class;

    public function definition(): array
    {
        return [
            'customer_id' => GhCustomer::factory(),
            'zone_id' => GhZone::factory(),
            'label' => 'Home',
            'building' => 'Villa 1',
            'street' => 'Street 1',
            'area' => 'Area 1',
            'directions' => null,
            'latitude' => null,
            'longitude' => null,
            'is_default' => false,
            'default_for_customer_id' => null,
        ];
    }
}
