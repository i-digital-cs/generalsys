<?php

namespace Tests\Unit\Payroll;

use App\Models\Attendance;
use App\Models\User;
use App\Services\Attendance\AttendanceService;
use App\Services\Payroll\PayrollAggregator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Task 1.6 — exactLateMinutes is date-aware:
 * pre-Oct uses stored late (historical protection); from 2026-10-01 uses grace-counted punch.
 */
class PayrollLateMinutesFromClockTest extends TestCase
{
    use RefreshDatabase;

    public function test_exact_late_uses_stored_late_before_grace_effective_date(): void
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

        $service = app(AttendanceService::class);

        $this->assertSame(0, $service->exactLateMinutes($attendance));
        $this->assertSame(104, $service->rawLateMinutes($attendance));
    }

    public function test_exact_late_uses_grace_counted_punch_from_effective_date(): void
    {
        $user = User::factory()->employee()->create([
            'work_start_time' => '20:00',
            'work_end_time' => '08:00',
        ]);

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-10-14',
            'shift_type' => Attendance::SHIFT_FULL_TIME,
            'clock_in' => '2026-10-14 21:44:00',
            'clock_out' => '2026-10-15 11:48:00',
            'planned_hours' => 12,
            'actual_hours' => 12,
            'raw_actual_hours' => 14.05,
            'status' => Attendance::STATUS_PRESENT,
            'late_minutes' => 0,
            'adjusted_late_minutes' => null,
        ]);
        $attendance->setRelation('user', $user);

        $this->assertSame(104, app(AttendanceService::class)->exactLateMinutes($attendance));
    }

    public function test_aggregator_sums_date_aware_exact_late(): void
    {
        $user = User::factory()->employee()->create([
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
        ]);

        // Pre-Oct: stored late 0 wins over 30 punch minutes.
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

        // Pre-Oct override wins.
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

        // October: punch 30 > grace → counted 30.
        Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-10-01',
            'shift_type' => Attendance::SHIFT_FULL_TIME,
            'clock_in' => '2026-10-01 10:30:00',
            'clock_out' => '2026-10-01 18:00:00',
            'planned_hours' => 8,
            'actual_hours' => 7.5,
            'status' => Attendance::STATUS_PRESENT,
            'late_minutes' => 0,
            'adjusted_late_minutes' => null,
        ]);

        $agg = app(PayrollAggregator::class)->aggregate($user, 9, 2026);
        $this->assertSame(20, $agg['late_minutes_total']);

        $oct = app(PayrollAggregator::class)->aggregate($user, 10, 2026);
        $this->assertSame(30, $oct['late_minutes_total']);
    }
}
