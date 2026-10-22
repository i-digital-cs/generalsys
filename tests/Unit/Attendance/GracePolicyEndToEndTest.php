<?php

namespace Tests\Unit\Attendance;

use App\Models\Attendance;
use App\Models\AttendanceSegment;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Services\Attendance\AttendanceCalculationEngine;
use App\Services\Attendance\AttendanceGracePolicy;
use App\Services\Attendance\AttendanceHistoryService;
use App\Services\Attendance\AttendanceService;
use App\Services\Attendance\AttendanceTimeCalculator;
use App\Services\Attendance\HoursComparisonService;
use App\Services\Payroll\PayrollAggregator;
use App\Services\Portal\PortalStatsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * End-to-end grace policy verification (threshold ≠ deduction; effective 2026-10-01).
 *
 * Rounding rule (documented, not changed): raw late/early = (int) Carbon::diffInMinutes(...)
 * which truncates toward zero (15m59s → 15 → counted 0; 16m00s → 16 → counted 16).
 */
class GracePolicyEndToEndTest extends TestCase
{
    use RefreshDatabase;

    private AttendanceGracePolicy $grace;

    private AttendanceService $attendance;

    private AttendanceCalculationEngine $engine;

    private AttendanceTimeCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->grace = app(AttendanceGracePolicy::class);
        $this->attendance = app(AttendanceService::class);
        $this->engine = app(AttendanceCalculationEngine::class);
        $this->calculator = app(AttendanceTimeCalculator::class);
    }

    /** @return list<array{0: int, 1: int}> raw → counted */
    public static function boundaryMinutesProvider(): array
    {
        return [
            [0, 0],
            [1, 0],
            [13, 0],
            [14, 0],
            [15, 0],
            [16, 16],
            [17, 17],
            [18, 18],
            [30, 30],
            [60, 60],
        ];
    }

    #[DataProvider('boundaryMinutesProvider')]
    public function test_policy_late_boundary_matrix(int $raw, int $counted): void
    {
        $this->assertSame($counted, $this->grace->countedLateMinutes($raw, '2026-10-01'));
        $this->assertSame($raw, $this->grace->countedLateMinutes($raw, '2026-09-30'));
    }

    #[DataProvider('boundaryMinutesProvider')]
    public function test_policy_early_boundary_matrix(int $raw, int $counted): void
    {
        $this->assertSame($counted, $this->grace->countedEarlyMinutes($raw, '2026-10-01'));
        $this->assertSame($raw, $this->grace->countedEarlyMinutes($raw, '2026-09-30'));
    }

    #[DataProvider('boundaryMinutesProvider')]
    public function test_clock_out_persists_counted_late_and_deficit(int $rawLate, int $countedLate): void
    {
        $employee = User::factory()->employee()->create([
            'work_start_time' => '09:00',
            'work_end_time' => '17:00',
        ]);

        $in = Carbon::parse('2026-10-05 09:00')->addMinutes($rawLate);
        $out = Carbon::parse('2026-10-05 17:00');

        $this->attendance->clockIn($employee, Attendance::SHIFT_FULL_TIME, $in);
        $row = $this->attendance->clockOut($employee, $out);

        $this->assertSame($rawLate, $this->attendance->rawLateMinutes($row));
        $this->assertSame($countedLate, (int) $row->late_minutes);
        $this->assertSame($countedLate, $this->attendance->exactLateMinutes($row));
        $this->assertSame($countedLate, $this->engine->exactLateMinutes($row));

        $summary = $this->calculator->summarize($row->fresh(['segments', 'user']), $this->attendance);
        $this->assertSame($rawLate, $summary->rawLateMinutes);
        $this->assertSame($countedLate, $summary->systemLateMinutes);
        $this->assertSame($countedLate, $summary->appliedLateMinutes);
        $this->assertSame($countedLate, (int) ($summary->durationBalance['counted_deficit_minutes'] ?? -1));
        $this->assertSame($countedLate, $summary->undertimeMinutes());

        $expectedStatus = $countedLate > 0
            ? Attendance::STATUS_LATE
            : Attendance::STATUS_PRESENT;
        $this->assertSame($expectedStatus, $row->status);

        // Actual worked duration must match punches (not rewritten by grace).
        $workedMinutes = (int) $in->diffInMinutes($out, false);
        $this->assertSame(
            round($workedMinutes / 60, 2),
            round((float) $row->raw_actual_hours, 2)
        );
    }

    #[DataProvider('boundaryMinutesProvider')]
    public function test_clock_out_persists_counted_early_and_deficit(int $rawEarly, int $countedEarly): void
    {
        $employee = User::factory()->employee()->create([
            'work_start_time' => '09:00',
            'work_end_time' => '17:00',
        ]);

        $in = Carbon::parse('2026-10-06 09:00');
        $out = Carbon::parse('2026-10-06 17:00')->subMinutes($rawEarly);

        $this->attendance->clockIn($employee, Attendance::SHIFT_FULL_TIME, $in);
        $row = $this->attendance->clockOut($employee, $out);

        $this->assertSame($rawEarly, $this->attendance->rawEarlyMinutes($row));
        $this->assertSame($countedEarly, (int) $row->early_departure_minutes);
        $this->assertSame($countedEarly, $this->attendance->exactEarlyMinutes($row));

        $summary = $this->calculator->summarize($row->fresh(['segments', 'user']), $this->attendance);
        $this->assertSame($countedEarly, $summary->systemEarlyMinutes);
        $this->assertSame($countedEarly, (int) ($summary->durationBalance['counted_deficit_minutes'] ?? -1));

        $expectedStatus = $countedEarly > 0
            ? Attendance::STATUS_EARLY_LEAVE
            : Attendance::STATUS_PRESENT;
        $this->assertSame($expectedStatus, $row->status);
    }

    /**
     * Seconds around the 15-minute threshold — documents truncation casting.
     *
     * @return list<array{0: string, 1: int, 2: int}> clock-in time, raw minutes, counted
     */
    public static function lateSecondsProvider(): array
    {
        return [
            ['09:15:00', 15, 0],
            ['09:15:01', 15, 0], // 15.016… → (int) 15
            ['09:15:59', 15, 0], // 15.983… → (int) 15 (NOT round)
            ['09:16:00', 16, 16],
        ];
    }

    #[DataProvider('lateSecondsProvider')]
    public function test_late_seconds_use_truncating_diff_in_minutes(string $clockIn, int $raw, int $counted): void
    {
        $employee = User::factory()->employee()->create([
            'work_start_time' => '09:00',
            'work_end_time' => '17:00',
        ]);

        $this->attendance->clockIn(
            $employee,
            Attendance::SHIFT_FULL_TIME,
            Carbon::parse('2026-10-07 '.$clockIn)
        );
        $row = $this->attendance->clockOut($employee, Carbon::parse('2026-10-07 17:00:00'));

        $this->assertSame($raw, $this->attendance->rawLateMinutes($row));
        $this->assertSame($counted, (int) $row->late_minutes);
        $this->assertSame($counted, $this->attendance->exactLateMinutes($row));
    }

    /**
     * @return list<array{0: string, 1: int, 2: int}>
     */
    public static function earlySecondsProvider(): array
    {
        return [
            ['16:45:00', 15, 0],
            ['16:44:59', 15, 0], // 15.016… early → (int) 15
            ['16:44:01', 15, 0], // 15.983… → (int) 15
            ['16:44:00', 16, 16],
        ];
    }

    #[DataProvider('earlySecondsProvider')]
    public function test_early_seconds_use_truncating_diff_in_minutes(string $clockOut, int $raw, int $counted): void
    {
        $employee = User::factory()->employee()->create([
            'work_start_time' => '09:00',
            'work_end_time' => '17:00',
        ]);

        $this->attendance->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-10-07 09:00:00'));
        $row = $this->attendance->clockOut($employee, Carbon::parse('2026-10-07 '.$clockOut));

        $this->assertSame($raw, $this->attendance->rawEarlyMinutes($row));
        $this->assertSame($counted, (int) $row->early_departure_minutes);
        $this->assertSame($counted, $this->attendance->exactEarlyMinutes($row));
    }

    public function test_never_subtracts_fifteen_from_violation(): void
    {
        foreach ([16, 17, 18, 30, 60] as $raw) {
            $this->assertSame($raw, $this->grace->countedLateMinutes($raw, '2026-10-01'));
            $this->assertSame($raw, $this->grace->countedEarlyMinutes($raw, '2026-10-01'));
            $this->assertNotSame($raw - 15, $this->grace->countedLateMinutes($raw, '2026-10-01'));
        }
    }

    public function test_late_and_early_together_independent_graces(): void
    {
        $employee = User::factory()->employee()->create([
            'work_start_time' => '09:00',
            'work_end_time' => '17:00',
        ]);

        // 10 late (exempt) + 20 early (full) → deficit 20 (30 raw shortfall − 10 exempt)
        $this->attendance->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-10-08 09:10'));
        $row = $this->attendance->clockOut($employee, Carbon::parse('2026-10-08 16:40'));

        $this->assertSame(0, (int) $row->late_minutes);
        $this->assertSame(20, (int) $row->early_departure_minutes);

        $summary = $this->calculator->summarize($row->fresh(['segments', 'user']), $this->attendance);
        $this->assertSame(10, $summary->rawLateMinutes);
        $this->assertSame(20, $summary->rawEarlyMinutes);
        $this->assertSame(20, (int) $summary->durationBalance['counted_deficit_minutes']);
        $this->assertSame(10, (int) $summary->durationBalance['exempt_arrival_minutes']);
        $this->assertSame(0, (int) $summary->durationBalance['exempt_departure_minutes']);
    }

    public function test_early_arrival_and_late_departure_surplus_not_auto_ot(): void
    {
        $employee = User::factory()->employee()->create([
            'work_start_time' => '09:00',
            'work_end_time' => '17:00',
        ]);

        $this->attendance->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-10-08 08:50'));
        $row = $this->attendance->clockOut($employee, Carbon::parse('2026-10-08 17:20'));

        $this->assertSame(0, (int) $row->late_minutes);
        $this->assertSame(0, (int) $row->early_departure_minutes);

        $summary = $this->calculator->summarize($row->fresh(['segments', 'user']), $this->attendance);
        $this->assertSame(AttendanceGracePolicy::CLASS_SURPLUS, $summary->hoursBalanceClassification());
        $this->assertSame(30, (int) $summary->durationBalance['counted_surplus_minutes']);
        $this->assertSame(0, (int) $row->recommended_overtime_minutes);
    }

    public function test_overnight_shift_applies_same_grace(): void
    {
        $employee = User::factory()->employee()->create([
            'work_start_time' => '21:00',
            'work_end_time' => '09:00',
        ]);

        // 10 min late into overnight → counted 0
        $this->attendance->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-10-09 21:10'));
        $row = $this->attendance->clockOut($employee, Carbon::parse('2026-10-10 09:00'));

        $this->assertSame(10, $this->attendance->rawLateMinutes($row));
        $this->assertSame(0, (int) $row->late_minutes);
        $this->assertSame(0, $this->attendance->exactLateMinutes($row));

        // Fresh day: 16 late → full
        $employee2 = User::factory()->employee()->create([
            'work_start_time' => '21:00',
            'work_end_time' => '09:00',
        ]);
        $this->attendance->clockIn($employee2, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-10-11 21:16'));
        $row2 = $this->attendance->clockOut($employee2, Carbon::parse('2026-10-12 09:00'));
        $this->assertSame(16, (int) $row2->late_minutes);
    }

    public function test_multi_segment_day_uses_first_in_and_last_out(): void
    {
        $employee = User::factory()->employee()->create([
            'work_start_time' => '09:00',
            'work_end_time' => '17:00',
        ]);

        $this->attendance->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-10-12 09:14'));
        $this->attendance->clockOut($employee, Carbon::parse('2026-10-12 12:00'));
        $this->attendance->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-10-12 13:00'));
        $row = $this->attendance->clockOut($employee, Carbon::parse('2026-10-12 16:50'));

        $this->assertSame(14, $this->attendance->rawLateMinutes($row));
        $this->assertSame(0, (int) $row->late_minutes);
        $this->assertSame(10, $this->attendance->rawEarlyMinutes($row));
        $this->assertSame(0, (int) $row->early_departure_minutes);
        $this->assertGreaterThanOrEqual(2, $row->segments()->count());
    }

    public function test_missing_checkout_does_not_invent_early_or_late_status_rewrite(): void
    {
        $employee = User::factory()->employee()->create([
            'work_start_time' => '09:00',
            'work_end_time' => '17:00',
        ]);

        $open = $this->attendance->clockIn(
            $employee,
            Attendance::SHIFT_FULL_TIME,
            Carbon::parse('2026-10-13 09:20')
        );

        $this->assertNull($open->clock_out);
        $this->assertSame(0, (int) $open->late_minutes);
        $this->assertSame(0, (int) $open->early_departure_minutes);
        $this->assertSame(Attendance::STATUS_PRESENT, $open->status);
        // Live exact still sees punch lateness after grace.
        $this->assertSame(20, $this->attendance->rawLateMinutes($open));
        $this->assertSame(20, $this->attendance->exactLateMinutes($open));
    }

    public function test_supervisor_checkout_applies_grace_on_recalculate(): void
    {
        $employee = User::factory()->employee()->create([
            'work_start_time' => '09:00',
            'work_end_time' => '17:00',
        ]);

        $row = $this->attendance->clockIn(
            $employee,
            Attendance::SHIFT_FULL_TIME,
            Carbon::parse('2026-10-14 09:10')
        );

        $segment = $row->segments()->whereNull('clock_out')->firstOrFail();
        $segment->update(['clock_out' => Carbon::parse('2026-10-14 16:50')]);

        $closed = $this->calculator->recalculate($row->fresh(['segments', 'user']), $this->attendance);

        $this->assertSame(0, (int) $closed->late_minutes);
        $this->assertSame(0, (int) $closed->early_departure_minutes);
        $this->assertNotNull($closed->clock_out);
    }

    public function test_shift_assignment_mismatch_still_uses_grace_when_window_resolves(): void
    {
        $leader = User::factory()->teamLeader()->create();
        $employee = User::factory()->employee()->create([
            'team_leader_id' => $leader->id,
            'work_start_time' => '09:00',
            'work_end_time' => '17:00',
        ]);

        ShiftAssignment::factory()->fullTime()->create([
            'user_id' => $employee->id,
            'assigned_by_tl_id' => $leader->id,
            'date' => '2026-10-15',
            'expected_start_time' => '10:00',
            'expected_end_time' => '18:00',
            'expected_hours' => 8,
            'status' => ShiftAssignment::STATUS_SCHEDULED,
        ]);

        // Clock relative to assignment 10:00 → 10 min late → counted 0
        $this->attendance->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-10-15 10:10'));
        $row = $this->attendance->clockOut($employee, Carbon::parse('2026-10-15 18:00'));

        $raw = $this->attendance->rawLateMinutes($row);
        $this->assertSame(10, $raw);
        $this->assertSame(0, (int) $row->late_minutes);
    }

    public function test_pre_october_does_not_apply_new_grace_on_recalculate(): void
    {
        $employee = User::factory()->employee()->create([
            'work_start_time' => '09:00',
            'work_end_time' => '17:00',
        ]);

        $attendance = Attendance::factory()->create([
            'user_id' => $employee->id,
            'date' => '2026-09-20',
            'shift_type' => Attendance::SHIFT_FULL_TIME,
            'clock_in' => '2026-09-20 09:10:00',
            'clock_out' => '2026-09-20 16:50:00',
            'planned_hours' => 8,
            'raw_actual_hours' => 7.67,
            'actual_hours' => 7.67,
            'late_minutes' => 10,
            'early_departure_minutes' => 10,
            'status' => Attendance::STATUS_LATE,
        ]);
        AttendanceSegment::query()->create([
            'attendance_id' => $attendance->id,
            'sequence' => 1,
            'clock_in' => '2026-09-20 09:10:00',
            'clock_out' => '2026-09-20 16:50:00',
        ]);

        $fresh = $this->calculator->recalculate(
            $attendance->fresh(['segments', 'user']),
            $this->attendance
        );

        $this->assertSame(10, (int) $fresh->late_minutes);
        $this->assertSame(10, (int) $fresh->early_departure_minutes);
        $this->assertSame(10, $this->attendance->exactLateMinutes($fresh));
        $this->assertFalse($this->grace->isActiveForAttendanceDate('2026-09-20'));
    }

    public function test_cross_screen_parity_engine_history_hours_payroll(): void
    {
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee()->create([
            'work_start_time' => '09:00',
            'work_end_time' => '17:00',
        ]);

        $this->attendance->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-10-16 09:16'));
        $row = $this->attendance->clockOut($employee, Carbon::parse('2026-10-16 17:00'));

        $exact = $this->engine->exactLateMinutes($row);
        $this->assertSame(16, $exact);

        $page = app(AttendanceHistoryService::class)->paginate($manager, [
            'employee_id' => $employee->id,
            'from' => '2026-10-16',
            'to' => '2026-10-16',
            'kind' => 'all',
            'evidence' => 'all',
            'page' => 1,
            'path' => '/',
        ]);
        $historyLate = (int) ($page->items()[0]['late_minutes'] ?? -1);
        $this->assertSame($exact, $historyLate);

        $month = app(HoursComparisonService::class)->monthForUser($employee, '2026-10');
        $this->assertSame($exact, (int) $month['totals']['late_minutes']);

        $live = app(PayrollAggregator::class)->aggregate($employee, 10, 2026);
        $this->assertSame($exact, (int) $live['late_minutes_total']);
        // Suggested = counted SSOT (same as late_minutes_total).
        $this->assertSame(16, (int) $live['suggested_late_minutes_total']);
        $this->assertSame($exact, (int) $live['suggested_late_minutes_total']);
        $this->assertSame(16, (int) $live['raw_late_minutes_total']);
    }

    public function test_payroll_suggested_equals_exact_within_grace_not_raw(): void
    {
        $employee = User::factory()->employee()->create([
            'work_start_time' => '09:00',
            'work_end_time' => '17:00',
        ]);

        $this->attendance->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-10-17 09:10'));
        $this->attendance->clockOut($employee, Carbon::parse('2026-10-17 17:00'));

        $live = app(PayrollAggregator::class)->aggregate($employee, 10, 2026);
        $this->assertSame(0, (int) $live['late_minutes_total']);
        $this->assertSame(0, (int) $live['suggested_late_minutes_total']);
        $this->assertSame(10, (int) $live['raw_late_minutes_total']);
    }

    public function test_portal_late_today_ignores_stale_status_when_counted_zero(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-18 12:00:00', config('app.timezone')));
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee()->create([
            'work_start_time' => '09:00',
            'work_end_time' => '17:00',
        ]);

        Attendance::factory()->create([
            'user_id' => $employee->id,
            'date' => '2026-10-18',
            'shift_type' => Attendance::SHIFT_FULL_TIME,
            'clock_in' => '2026-10-18 09:10:00',
            'clock_out' => '2026-10-18 17:00:00',
            'late_minutes' => 0,
            'status' => Attendance::STATUS_LATE, // stale label
            'planned_hours' => 8,
            'actual_hours' => 7.83,
            'daily_report' => 'ok',
        ]);

        $snap = app(PortalStatsService::class)->snapshot($manager);
        $this->assertSame(0, $snap['cards']['late_today']);
    }

    public function test_portal_late_today_counts_when_exact_positive(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-19 12:00:00', config('app.timezone')));
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee()->create([
            'work_start_time' => '09:00',
            'work_end_time' => '17:00',
        ]);

        Attendance::factory()->create([
            'user_id' => $employee->id,
            'date' => '2026-10-19',
            'shift_type' => Attendance::SHIFT_FULL_TIME,
            'clock_in' => '2026-10-19 09:20:00',
            'clock_out' => '2026-10-19 17:00:00',
            'late_minutes' => 0,
            'status' => Attendance::STATUS_PRESENT,
            'planned_hours' => 8,
            'actual_hours' => 7.67,
            'daily_report' => 'ok',
        ]);

        $snap = app(PortalStatsService::class)->snapshot($manager);
        $this->assertSame(1, $snap['cards']['late_today']);
    }
}
