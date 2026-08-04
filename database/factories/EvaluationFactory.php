<?php

namespace Database\Factories;

use App\Models\Evaluation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Evaluation> */
class EvaluationFactory extends Factory
{
    protected $model = Evaluation::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->employee(),
            'team_leader_id' => User::factory()->teamLeader(),
            'month' => 8,
            'year' => 2026,
            'performance_score' => 90,
            'behavior_score' => 90,
            'productivity_score' => 90,
            'interest_return_score' => 90,
            'attendance_score' => 90,
            'total_score' => 90,
            'evaluation_text' => null,
        ];
    }
}
