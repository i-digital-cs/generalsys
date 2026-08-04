<?php

namespace Database\Factories;

use App\Models\RestDay;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RestDay> */
class RestDayFactory extends Factory
{
    protected $model = RestDay::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->employee(),
            'date' => '2026-08-07',
            'created_by' => User::factory()->teamLeader(),
        ];
    }
}
