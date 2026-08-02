<?php

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Attendance> */
class AttendanceFactory extends Factory
{
    protected $model = Attendance::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->employee(),
            'date' => now()->toDateString(),
            'shift_type' => Attendance::SHIFT_FULL_TIME,
            'planned_hours' => 8,
            'actual_hours' => 0,
            'status' => Attendance::STATUS_PRESENT,
        ];
    }
}
