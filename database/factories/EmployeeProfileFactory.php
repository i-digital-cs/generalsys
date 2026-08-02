<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EmployeeProfile>
 */
class EmployeeProfileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'employee_code' => 'EMP-'.fake()->unique()->numberBetween(1000, 999999),
            'national_id' => fake()->numerify('##############'),
            'birth_date' => fake()->dateTimeBetween('-55 years', '-20 years')->format('Y-m-d'),
            'address' => fake()->address(),
            'phone' => fake()->phoneNumber(),
            'gender' => fake()->randomElement(['MALE', 'FEMALE']),
            'religion' => null,
            'marital_status' => fake()->randomElement(['SINGLE', 'MARRIED', 'DIVORCED', 'WIDOWED']),
        ];
    }
}
