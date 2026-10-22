<?php

namespace Tests\Unit\Attendance;

use App\Models\Attendance;
use App\Models\User;
use App\Services\Attendance\AttendanceCalculationEngine;
use App\Services\Attendance\AttendanceCalculationResult;
use App\Services\Attendance\AttendanceService;
use App\Services\Attendance\CheckoutProvenance;
use App\Services\Attendance\HoursComparisonService;
use App\Services\Payroll\PayrollAggregator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SSOT: engine, history/hours totals, and payroll late must agree on exact late/early.
 */
class AttendanceCalculationEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_result_carries_provenance_and_exact_late_for_grace_day(): void
    {
        $user = User::factory()->employee()->create([
            'work_start_time' => '09:00',
            'work_end_time' => '17:00',
        ]);

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-10-10',
            'shift_type' => Attendance::SHIFT_FULL_TIME,
            'clock_in' => '2026-10-10 09:16:00',
            'clock_out' => '2026-10-10 17:00:00',
            'actual_hours' => 7.73,
            'raw_actual_hours' => 7.73,
            'planned_hours' => 8,
            'late_minutes' => 0,
            'early_departure_minutes' => 0,
            'is_auto_clock_out' => false,
            'force_closed_by' => null,
        ]);
        $attendance->setRelation('user', $user);
        $attendance->load(['segments', 'gaps']);

        $result = app(AttendanceCalculationEngine::class)->forAttendance($attendance);

        $this->assertSame(CheckoutProvenance::EMPLOYEE, $result->checkoutProvenance);
        $this->assertSame(AttendanceCalculationResult::POLICY_VERSION, $result->policyVersion);
        $this->assertTrue($result->gracePolicyActive);
        $this->assertSame(16, $result->countedLateMinutes());
        $this->assertSame(16, $result->rawLateMinutes());
        $this->assertSame(0, $result->countedEarlyMinutes());
    }

    public function test_auto_close_provenance_is_auto_stale(): void
    {
        $user = User::factory()->employee()->create([
            'work_start_time' => '09:00',
            'work_end_time' => '17:00',
        ]);
        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-10-11',
            'shift_type' => Attendance::SHIFT_FULL_TIME,
            'clock_in' => '2026-10-11 09:00:00',
            'clock_out' => '2026-10-11 17:00:00',
            'actual_hours' => 8,
            'raw_actual_hours' => 8,
            'planned_hours' => 8,
            'is_auto_clock_out' => true,
            'force_closed_by' => null,
        ]);
        $attendance->load(['segments', 'gaps', 'user']);

        $result = app(AttendanceCalculationEngine::class)->forAttendance($attendance);
        $this->assertSame(CheckoutProvenance::AUTO_STALE, $result->checkoutProvenance);
    }

    public function test_hours_month_totals_match_engine_exact_late_and_early(): void
    {
        $user = User::factory()->employee()->create([
            'work_start_time' => '09:00',
            'work_end_time' => '17:00',
        ]);

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-10-12',
            'shift_type' => Attendance::SHIFT_FULL_TIME,
            'status' => Attendance::STATUS_PRESENT,
            'clock_in' => '2026-10-12 09:20:00',
            'clock_out' => '2026-10-12 16:40:00',
            'actual_hours' => 7.33,
            'raw_actual_hours' => 7.33,
            'planned_hours' => 8,
            'late_minutes' => 0,
            'early_departure_minutes' => 0,
        ]);
        $attendance->setRelation('user', $user);

        $engine = app(AttendanceCalculationEngine::class);
        $service = app(AttendanceService::class);
        $this->assertSame(20, $service->exactLateMinutes($attendance));
        $this->assertSame(20, $service->exactEarlyMinutes($attendance));

        $month = app(HoursComparisonService::class)->monthForUser($user, '2026-10');
        $this->assertSame(20, $month['totals']['late_minutes']);
        $this->assertSame(20, $month['totals']['early_minutes']);
        $this->assertSame(
            $engine->exactLateMinutes($attendance),
            $month['totals']['late_minutes'],
        );
    }

    public function test_payroll_aggregator_matches_engine_exact_late(): void
    {
        $user = User::factory()->employee()->create([
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
        ]);
        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-10-14',
            'shift_type' => Attendance::SHIFT_FULL_TIME,
            'clock_in' => '2026-10-14 10:30:00',
            'clock_out' => '2026-10-14 18:00:00',
            'planned_hours' => 8,
            'actual_hours' => 7.5,
            'raw_actual_hours' => 7.5,
            'late_minutes' => 0,
            'adjusted_late_minutes' => null,
        ]);
        $attendance->setRelation('user', $user);

        $engineLate = app(AttendanceCalculationEngine::class)->exactLateMinutes($attendance);
        $agg = app(PayrollAggregator::class)->aggregate($user, 10, 2026);

        $this->assertSame(30, $engineLate);
        $this->assertSame($engineLate, (int) $agg['late_minutes_total']);
    }

    public function test_grace_threshold_15_vs_16(): void
    {
        $user = User::factory()->employee()->create([
            'work_start_time' => '09:00',
            'work_end_time' => '17:00',
        ]);
        $engine = app(AttendanceCalculationEngine::class);

        $within = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-10-15',
            'shift_type' => Attendance::SHIFT_FULL_TIME,
            'clock_in' => '2026-10-15 09:15:00',
            'clock_out' => '2026-10-15 17:00:00',
            'actual_hours' => 7.75,
            'raw_actual_hours' => 7.75,
            'planned_hours' => 8,
            'late_minutes' => 0,
        ]);
        $within->setRelation('user', $user);
        $this->assertSame(0, $engine->exactLateMinutes($within));

        $over = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-10-16',
            'shift_type' => Attendance::SHIFT_FULL_TIME,
            'clock_in' => '2026-10-16 09:16:00',
            'clock_out' => '2026-10-16 17:00:00',
            'actual_hours' => 7.73,
            'raw_actual_hours' => 7.73,
            'planned_hours' => 8,
            'late_minutes' => 0,
        ]);
        $over->setRelation('user', $user);
        $this->assertSame(16, $engine->exactLateMinutes($over));
    }
}
