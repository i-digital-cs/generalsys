<?php

namespace Tests\Unit\Payroll;

use App\Models\Attendance;
use App\Models\User;
use App\Services\Attendance\AttendanceService;
use App\Services\Payroll\PayrollAggregator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollLateMinutesFromClockTest extends TestCase
{
    use RefreshDatabase;

    public function test_exact_late_uses_clock_in_vs_shift_start_when_column_is_zero(): void
    {
        $user = User::factory()->employee()->create([
            'work_start_time' => '20:00',
            'work_end_time' => '08:00',
        ]);

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-09-14',
            'shift_type' => Attendance::SHIFT_FULL_TIME,
            'clock_in' => '2026-09-14 21:44:00',
            'clock_out' => '2026-09-15 11:48:00',
            'planned_hours' => 12,
            'actual_hours' => 12,
            'raw_actual_hours' => 14.05,
            'status' => Attendance::STATUS_PRESENT,
            'late_minutes' => 0,
            'adjusted_late_minutes' => null,
        ]);
        $attendance->setRelation('user', $user);

        $late = app(AttendanceService::class)->exactLateMinutes($attendance);

        $this->assertSame(104, $late);
    }

    public function test_aggregator_sums_exact_late_not_stored_zero(): void
    {
        $user = User::factory()->employee()->create([
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
        ]);

        Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-09-01',
            'shift_type' => Attendance::SHIFT_FULL_TIME,
            'clock_in' => '2026-09-01 10:30:00',
            'clock_out' => '2026-09-01 18:00:00',
            'planned_hours' => 8,
            'actual_hours' => 7.5,
            'status' => Attendance::STATUS_PRESENT,
            'late_minutes' => 0,
            'adjusted_late_minutes' => null,
        ]);

        Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-09-02',
            'shift_type' => Attendance::SHIFT_FULL_TIME,
            'clock_in' => '2026-09-02 10:15:00',
            'clock_out' => '2026-09-02 18:00:00',
            'planned_hours' => 8,
            'actual_hours' => 7.75,
            'status' => Attendance::STATUS_PRESENT,
            'late_minutes' => 0,
            'adjusted_late_minutes' => 20,
        ]);

        $agg = app(PayrollAggregator::class)->aggregate($user, 9, 2026);

        $this->assertSame(50, $agg['late_minutes_total']);
    }
}
