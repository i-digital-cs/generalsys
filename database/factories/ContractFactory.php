<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Contract>
 */
class ContractFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-2 years', 'now');

        return [
            'user_id' => User::factory(),
            'hire_date' => $start->format('Y-m-d'),
            'contract_start' => $start->format('Y-m-d'),
            'contract_end' => (clone $start)->modify('+1 year')->format('Y-m-d'),
            'contract_type' => 'سنة',
            'is_active' => true,
        ];
    }
}
