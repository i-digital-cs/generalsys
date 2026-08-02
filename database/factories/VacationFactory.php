<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Vacation;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Vacation> */
class VacationFactory extends Factory
{
    protected $model = Vacation::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->employee(),
            'type' => Vacation::TYPE_ANNUAL,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'days_requested' => 2,
            'status' => Vacation::STATUS_PENDING,
        ];
    }
}
