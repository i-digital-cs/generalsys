<?php

namespace Database\Factories\GeneralHouse;

use App\Models\GeneralHouse\GhCustomer;
use App\Support\GeneralHouse\GhCatalogStatus;
use App\Support\GeneralHouse\GhCustomerType;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GhCustomer> */
class GhCustomerFactory extends Factory
{
    protected $model = GhCustomer::class;

    public function definition(): array
    {
        return [
            'type' => GhCustomerType::B2C,
            'name_ar' => 'عميل '.fake()->unique()->numerify('###'),
            'name_en' => 'Customer '.fake()->unique()->numerify('###'),
            'phone' => '05'.fake()->numerify('########'),
            'email' => fake()->unique()->safeEmail(),
            'billing_name' => null,
            'billing_address' => null,
            'tax_registration_number' => null,
            'status' => GhCatalogStatus::ACTIVE,
            'notes' => null,
        ];
    }

    public function b2b(): static
    {
        return $this->state(fn () => [
            'type' => GhCustomerType::B2B,
            'billing_name' => 'Billing Co',
            'tax_registration_number' => 'TRN'.fake()->numerify('#########'),
        ]);
    }
}
