<?php

namespace Tests\Unit\Attendance;

use App\Models\Attendance;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Services\Attendance\HoursComparisonService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T-2.13 — planned (assignments / profile window) vs actual hours.
 */
class HoursComparisonServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_assignment_hours_used_when_present(): void
    {
        $user = User::factory()->employee()->create([
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
        ]);

        ShiftAssignment::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-08-05',
            'shift_type' => Attendance::SHIFT_ONLINE_HALF,
            'expected_start_time' => '10:00',
            'expected_end_time' => '14:00',
            'expected_hours' => 4,
            'status' => ShiftAssignment::STATUS_SCHEDULED,
        ]);

        Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-08-05',
            'shift_type' => Attendance::SHIFT_ONLINE_HALF,
            'clock_in' => '2026-08-05 10:00:00',
            'clock_out' => '2026-08-05 13:30:00',
            'actual_hours' => 3.5,
            'planned_hours' => 4,
        ]);

        $month = app(HoursComparisonService::class)->monthForUser($user, '2026-08');
        $day = collect($month['days'])->firstWhere('date', '2026-08-05');

        $this->assertFalse($day['from_profile']);
        $this->assertSame(4.0, $day['planned_hours']);
        $this->assertSame(3.5, $day['actual_hours']);
        $this->assertSame(87.5, $day['pct']);
    }

    public function test_cancelled_assignment_ignored_and_profile_fallback(): void
    {
        $user = User::factory()->employee()->create([
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
        ]);

        ShiftAssignment::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-08-07',
            'shift_type' => Attendance::SHIFT_FULL_TIME,
            'expected_hours' => 8,
            'status' => ShiftAssignment::STATUS_CANCELLED,
        ]);

        $month = app(HoursComparisonService::class)->monthForUser($user, '2026-08');
        $day = collect($month['days'])->firstWhere('date', '2026-08-07');

        $this->assertTrue($day['from_profile']);
        $this->assertSame(8.0, $day['planned_hours']);
    }

    public function test_team_leader_cannot_view_other_team_member(): void
    {
        $leader = User::factory()->teamLeader()->create();
        $other = User::factory()->employee()->create([
            'team_leader_id' => User::factory()->teamLeader()->create()->id,
        ]);

        $this->assertFalse(app(HoursComparisonService::class)->actorMayView($leader, $other));
    }
}
