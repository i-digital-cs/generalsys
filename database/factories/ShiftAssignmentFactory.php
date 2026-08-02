<?php

namespace Database\Factories;

use App\Models\ShiftAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ShiftAssignment> */
class ShiftAssignmentFactory extends Factory
{
    protected $model = ShiftAssignment::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->employee(),
            'assigned_by_tl_id' => User::factory()->teamLeader(),
            'date' => now()->toDateString(),
            'shift_type' => ShiftAssignment::TYPE_OVERTIME,
            'expected_start_time' => '18:00',
            'expected_end_time' => '22:00',
            'expected_hours' => 4,
            'multiplier' => 1.5,
            'status' => ShiftAssignment::STATUS_SCHEDULED,
        ];
    }
}
