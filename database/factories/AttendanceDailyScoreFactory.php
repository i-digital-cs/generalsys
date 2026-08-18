<?php

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\AttendanceDailyScore;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AttendanceDailyScore> */
class AttendanceDailyScoreFactory extends Factory
{
    protected $model = AttendanceDailyScore::class;

    public function definition(): array
    {
        return [
            'attendance_id' => Attendance::factory(),
            'team_leader_id' => User::factory()->teamLeader(),
            'scored_by' => fn (array $attrs) => $attrs['team_leader_id'],
            'performance_score' => 90,
            'behavior_score' => 90,
            'productivity_score' => 90,
            'interest_return_score' => 90,
        ];
    }
}
