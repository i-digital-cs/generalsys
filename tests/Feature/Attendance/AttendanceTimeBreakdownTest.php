<?php

namespace Tests\Feature\Attendance;

use App\Models\Attendance;
use App\Models\User;
use App\Services\Attendance\AttendanceService;
use App\Services\Attendance\AttendanceTimeCalculator;
use App\Services\Attendance\AttendanceTimeReviewService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * R4 — full time breakdown (gaps, overtime, metrics) on views.
 */
class AttendanceTimeBreakdownTest extends TestCase
{
    use RefreshDatabase;

    public function test_summarize_includes_gaps_and_overtime_delta(): void
    {
        $leader = User::factory()->teamLeader()->create();
        $employee = User::factory()->employee()->create([
            'team_leader_id' => $leader->id,
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
        ]);

        $service = app(AttendanceService::class);
        $service->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-10-03 10:00'));
        $service->clockOut($employee, Carbon::parse('2026-10-03 11:00'));
        $attendance = $service->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-10-03 12:00'));
        $final = $service->clockOut($employee, Carbon::parse('2026-10-03 20:00'));

        app(AttendanceTimeReviewService::class)->tlRecommendOvertime($leader, $final, 60, 'RECOMMEND');

        $summary = app(AttendanceTimeCalculator::class)->summarize($final->fresh(['segments', 'gaps']), $service);

        $this->assertSame(60, $summary->gapMinutesTotal);
        $this->assertCount(1, $summary->gaps);
        $this->assertSame(60, $summary->overtimeDeltaMinutes);
        $this->assertSame(60, $summary->recommendedOvertimeMinutes);
        $this->assertSame('10:00', $summary->windowStart);
        $this->assertSame('18:00', $summary->windowEnd);
        $this->assertSame('10:00', $summary->clockInAt);
        $this->assertSame('inside_window', $summary->clockInWindowPosition);
    }

    public function test_summarize_marks_undertime_and_after_window_clock_out(): void
    {
        $employee = User::factory()->employee()->create([
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
        ]);

        $service = app(AttendanceService::class);
        $service->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-10-04 10:00'));
        $final = $service->clockOut($employee, Carbon::parse('2026-10-04 17:19'));

        $summary = app(AttendanceTimeCalculator::class)->summarize($final->fresh(['segments', 'gaps']), $service);

        $this->assertSame(-41, $summary->overtimeDeltaMinutes);
        $this->assertSame(41, $summary->undertimeMinutes());
        $this->assertSame('17:19', $summary->clockOutAt);
        $this->assertSame('inside_window', $summary->clockOutWindowPosition);

        $this->actingAs(User::factory()->manager()->create())
            ->get(route('team-leader.attendance', [
                'from' => '2026-10-04',
                'to' => '2026-10-04',
                'employee_id' => $employee->id,
            ]))
            ->assertOk()
            ->assertSee(__('attendance_review.window.planned'), false)
            ->assertSee(__('attendance_review.hours_balance.short', ['minutes' => 41]), false)
            ->assertDontSee(__('attendance_review.breakdown.overtime_delta'), false);
    }

    public function test_employee_dashboard_shows_time_metrics(): void
    {
        $employee = User::factory()->employee()->create([
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
        ]);

        $service = app(AttendanceService::class);
        // On/after 2026-10-01: 20 min late exceeds grace → counted late; early leave creates deficit.
        $service->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-10-03 10:20'));
        $service->clockOut($employee, Carbon::parse('2026-10-03 18:00'));

        Carbon::setTestNow(Carbon::parse('2026-10-03 20:00'));

        $this->actingAs($employee)
            ->get(route('employee.dashboard'))
            ->assertOk()
            ->assertSee(__('attendance_review.breakdown.raw'), false)
            ->assertSee(__('attendance_review.late_minutes'), false)
            ->assertSee(__('attendance_review.hours_balance.classification.deficit'), false);

        Carbon::setTestNow();
    }

    public function test_employee_report_shows_time_breakdown(): void
    {
        $employee = User::factory()->employee()->create([
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
        ]);

        $service = app(AttendanceService::class);
        $service->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-10-03 10:00'));
        $service->clockOut($employee, Carbon::parse('2026-10-03 18:00'));

        $this->actingAs($employee)
            ->get(route('employee.report', ['date' => '2026-10-03']))
            ->assertOk()
            ->assertSee(__('attendance_review.comparison.title'), false)
            ->assertSee(__('attendance_review.planned_hours'), false);
    }

    public function test_attendance_history_lists_gap_and_overtime_columns(): void
    {
        $leader = User::factory()->teamLeader()->create();
        $employee = User::factory()->employee()->create([
            'team_leader_id' => $leader->id,
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
        ]);

        $service = app(AttendanceService::class);
        $service->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-10-03 10:00'));
        $service->clockOut($employee, Carbon::parse('2026-10-03 11:00'));
        $attendance = $service->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-10-03 12:00'));
        $final = $service->clockOut($employee, Carbon::parse('2026-10-03 18:00'));

        app(AttendanceTimeReviewService::class)->tlRecommendOvertime($leader, $final, 30, 'RECOMMEND');

        $this->actingAs($leader)
            ->get(route('team-leader.attendance', [
                'from' => '2026-10-03',
                'to' => '2026-10-03',
                'kind' => 'all',
                'evidence' => 'all',
            ]))
            ->assertOk()
            ->assertSee(__('attendance_review.breakdown.gap'), false)
            ->assertSee(__('attendance_review.overtime_minutes'), false)
            ->assertSee('60', false);
    }

    public function test_summarize_uses_live_profile_planned_hours_for_overtime_delta(): void
    {
        $employee = User::factory()->employee()->create([
            'work_start_time' => '21:00',
            'work_end_time' => '09:00',
            'shift_types_default' => [
                ['type' => Attendance::SHIFT_FULL_TIME, 'start' => '21:00', 'end' => '09:00'],
            ],
        ]);

        $service = app(AttendanceService::class);
        $service->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-09-12 21:09:00'));
        $final = $service->clockOut($employee, Carbon::parse('2026-09-13 16:18:00'));

        // Stale snapshot as if clock-in froze 8h before profile was set to 12h.
        $final->update(['planned_hours' => 8, 'actual_hours' => 8]);

        $summary = app(AttendanceTimeCalculator::class)->summarize($final->fresh(['segments', 'gaps', 'user']), $service);

        $this->assertSame(12.0, $summary->plannedHours);
        // 19.15h raw − 12h planned ≈ 429 extra minutes (not 669 vs stale 8h).
        $this->assertSame(429, $summary->overtimeDeltaMinutes);
        $this->assertSame(429, $summary->systemSuggestedOvertimeMinutes);
        $this->assertSame(12.0, $final->fresh(['user'])->resolvedPlannedHours());
        $this->assertSame(429, $final->fresh(['user'])->overtimeDeltaMinutes());

        app(AttendanceTimeCalculator::class)->recalculate($final->fresh(['segments', 'user']), $service);
        $this->assertSame('12.00', $final->fresh()->planned_hours);
        $this->assertSame('12.00', $final->fresh()->actual_hours);
    }
}
