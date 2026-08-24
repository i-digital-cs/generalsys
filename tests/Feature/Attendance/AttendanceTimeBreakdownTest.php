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
        $service->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-08-03 10:00'));
        $service->clockOut($employee, Carbon::parse('2026-08-03 11:00'));
        $attendance = $service->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-08-03 12:00'));
        $final = $service->clockOut($employee, Carbon::parse('2026-08-03 20:00'));

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
        $service->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-08-04 10:00'));
        $final = $service->clockOut($employee, Carbon::parse('2026-08-04 17:19'));

        $summary = app(AttendanceTimeCalculator::class)->summarize($final->fresh(['segments', 'gaps']), $service);

        $this->assertSame(-41, $summary->overtimeDeltaMinutes);
        $this->assertSame(41, $summary->undertimeMinutes());
        $this->assertSame('17:19', $summary->clockOutAt);
        $this->assertSame('inside_window', $summary->clockOutWindowPosition);

        $this->actingAs(User::factory()->manager()->create())
            ->get(route('team-leader.attendance', [
                'from' => '2026-08-04',
                'to' => '2026-08-04',
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
        $service->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-08-03 10:15'));
        $service->clockOut($employee, Carbon::parse('2026-08-03 18:15'));

        Carbon::setTestNow(Carbon::parse('2026-08-03 20:00'));

        $this->actingAs($employee)
            ->get(route('employee.dashboard'))
            ->assertOk()
            ->assertSee(__('attendance_review.breakdown.raw'), false)
            ->assertSee(__('attendance_review.late_minutes'), false);

        Carbon::setTestNow();
    }

    public function test_employee_report_shows_time_breakdown(): void
    {
        $employee = User::factory()->employee()->create([
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
        ]);

        $service = app(AttendanceService::class);
        $service->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-08-03 10:00'));
        $service->clockOut($employee, Carbon::parse('2026-08-03 18:00'));

        $this->actingAs($employee)
            ->get(route('employee.report', ['date' => '2026-08-03']))
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
        $service->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-08-03 10:00'));
        $service->clockOut($employee, Carbon::parse('2026-08-03 11:00'));
        $attendance = $service->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-08-03 12:00'));
        $final = $service->clockOut($employee, Carbon::parse('2026-08-03 18:00'));

        app(AttendanceTimeReviewService::class)->tlRecommendOvertime($leader, $final, 30, 'RECOMMEND');

        $this->actingAs($leader)
            ->get(route('team-leader.attendance', [
                'from' => '2026-08-03',
                'to' => '2026-08-03',
                'kind' => 'all',
                'evidence' => 'all',
            ]))
            ->assertOk()
            ->assertSee(__('attendance_review.breakdown.gap'), false)
            ->assertSee(__('attendance_review.overtime_minutes'), false)
            ->assertSee('60', false);
    }
}
