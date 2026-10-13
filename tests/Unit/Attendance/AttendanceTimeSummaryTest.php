<?php

namespace Tests\Unit\Attendance;

use App\Models\Attendance;
use App\Models\User;
use App\Services\Attendance\AttendanceService;
use App\Services\Attendance\AttendanceTimeCalculator;
use App\Services\Attendance\AttendanceTimeReviewService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * R2 — system recommended vs payroll-credited time summary.
 */
class AttendanceTimeSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_summarize_matches_system_when_no_override(): void
    {
        $employee = User::factory()->employee()->create([
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
        ]);

        $service = app(AttendanceService::class);
        $service->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-08-03 10:00'));
        $attendance = $service->clockOut($employee, Carbon::parse('2026-08-03 18:00'));

        $summary = app(AttendanceTimeCalculator::class)->summarize($attendance, $service);

        $this->assertFalse($summary->open);
        $this->assertSame(8.0, $summary->systemRawHours);
        $this->assertSame(8.0, $summary->systemCreditedHours);
        $this->assertSame(8.0, $summary->appliedCreditedHours);
        $this->assertFalse($summary->hasOverrides());
    }

    public function test_summarize_shows_override_after_tl_adjustment(): void
    {
        $leader = User::factory()->teamLeader()->create();
        $employee = User::factory()->employee()->create([
            'team_leader_id' => $leader->id,
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
        ]);

        $service = app(AttendanceService::class);
        $service->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-10-03 10:15'));
        $attendance = $service->clockOut($employee, Carbon::parse('2026-10-03 18:15'));

        $updated = app(AttendanceTimeReviewService::class)->applyAdjustments($leader, $attendance, [
            'credited_hours' => 8,
            'adjusted_late_minutes' => 5,
        ]);

        $summary = app(AttendanceTimeCalculator::class)->summarize($updated, $service);

        $this->assertSame(8.0, $summary->systemCreditedHours);
        $this->assertSame(8.0, $summary->appliedCreditedHours);
        // 15 min late is within arrival grace → system counted late = 0; applied keeps TL override.
        $this->assertSame(0, $summary->systemLateMinutes);
        $this->assertSame(15, $summary->rawLateMinutes);
        $this->assertSame(5, $summary->appliedLateMinutes);
        $this->assertTrue($summary->creditedOverridden);
        $this->assertTrue($summary->lateOverridden);
    }

    public function test_reset_to_system_clears_overrides(): void
    {
        $leader = User::factory()->teamLeader()->create();
        $employee = User::factory()->employee()->create([
            'team_leader_id' => $leader->id,
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
        ]);

        $service = app(AttendanceService::class);
        $service->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-10-03 10:15'));
        $attendance = $service->clockOut($employee, Carbon::parse('2026-10-03 18:15'));

        $reviews = app(AttendanceTimeReviewService::class);
        $reviews->applyAdjustments($leader, $attendance, [
            'credited_hours' => 7,
            'adjusted_late_minutes' => 0,
        ]);

        $reset = $reviews->applyAdjustments($leader, $attendance->fresh(['segments', 'user']), [
            'credited_hours' => null,
            'adjusted_late_minutes' => null,
        ]);

        $summary = app(AttendanceTimeCalculator::class)->summarize($reset, $service);

        $this->assertFalse($summary->hasOverrides());
        $this->assertSame(0, $summary->appliedLateMinutes);
        $this->assertSame(0, $summary->systemLateMinutes);
        $this->assertSame(15, $summary->rawLateMinutes);
        $this->assertSame(8.0, $summary->appliedCreditedHours);
    }

    public function test_grace_within_arrival_and_departure_is_balanced(): void
    {
        $employee = User::factory()->employee()->create([
            'work_start_time' => '09:00',
            'work_end_time' => '17:00',
        ]);

        $service = app(AttendanceService::class);
        $service->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-10-03 09:10'));
        $attendance = $service->clockOut($employee, Carbon::parse('2026-10-03 16:50'));

        $summary = app(AttendanceTimeCalculator::class)->summarize($attendance->fresh(['segments', 'user']), $service);

        $this->assertSame(0, $summary->systemLateMinutes);
        $this->assertSame(0, $summary->systemEarlyMinutes);
        $this->assertSame(0, $summary->undertimeMinutes());
        $this->assertSame('balanced', $summary->hoursBalanceClassification());
        $this->assertSame(0, (int) $attendance->fresh()->late_minutes);
        $this->assertSame(0, (int) $attendance->fresh()->early_departure_minutes);
    }

    public function test_late_beyond_grace_counts_full_and_deficit(): void
    {
        $employee = User::factory()->employee()->create([
            'work_start_time' => '09:00',
            'work_end_time' => '17:00',
        ]);

        $service = app(AttendanceService::class);
        $service->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-10-03 09:16'));
        $attendance = $service->clockOut($employee, Carbon::parse('2026-10-03 17:00'));

        $summary = app(AttendanceTimeCalculator::class)->summarize($attendance->fresh(['segments', 'user']), $service);

        $this->assertSame(16, $summary->systemLateMinutes);
        $this->assertSame(16, $summary->undertimeMinutes());
        $this->assertSame('deficit', $summary->hoursBalanceClassification());
        $this->assertSame(16, (int) $attendance->fresh()->late_minutes);
    }
}
