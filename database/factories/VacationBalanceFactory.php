<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\VacationBalance;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<VacationBalance> */
class VacationBalanceFactory extends Factory
{
    protected $model = VacationBalance::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->employee(),
            'year' => (int) now()->format('Y'),
            'accrued_days' => 21,
            'used_days' => 0,
            'pending_days' => 0,
            'remaining_days' => 21,
        ];
    }
}
