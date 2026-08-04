<?php

namespace Database\Factories;

use App\Models\Advance;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Advance> */
class AdvanceFactory extends Factory
{
    protected $model = Advance::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->employee(),
            'amount' => 500,
            'date_given' => '2026-08-01',
            'deduction_amount_monthly' => 100,
            'remaining_amount' => 500,
            'status' => Advance::STATUS_ACTIVE,
        ];
    }
}
