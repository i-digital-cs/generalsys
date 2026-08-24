<?php

namespace Tests\Feature\Attendance;

use App\Models\Attendance;
use App\Models\AttendanceGap;
use App\Models\User;
use App\Services\Attendance\AttendanceGapService;
use App\Services\Attendance\AttendanceService;
use App\Services\Attendance\AttendanceTimeReviewService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T-9.1 — multi-punch same shift, gaps, credited hours, overtime review.
 */
class AttendanceMultiPunchTest extends TestCase
{
    use RefreshDatabase;

    public function test_multi_punch_creates_gap_and_aggregates_hours(): void
    {
        $employee = User::factory()->employee()->create([
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
        ]);

        $service = app(AttendanceService::class);
        $service->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-08-03 10:00'));
        $service->clockOut($employee, Carbon::parse('2026-08-03 11:00'));
        $attendance = $service->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-08-03 12:00'));
        $final = $service->clockOut($employee, Carbon::parse('2026-08-03 18:00'));

        $this->assertSame('7.00', $final->raw_actual_hours);
        $this->assertSame('7.00', $final->actual_hours);
        $this->assertDatabaseHas('attendance_gaps', [
            'attendance_id' => $attendance->id,
            'minutes' => 60,
            'tl_recommendation' => AttendanceGap::TL_PENDING,
        ]);
    }

    public function test_credited_hours_capped_at_planned_eight(): void
    {
        $employee = User::factory()->employee()->create([
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
        ]);

        $service = app(AttendanceService::class);
        $service->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-08-03 10:00'));
        $final = $service->clockOut($employee, Carbon::parse('2026-08-03 19:00'));

        $this->assertSame('9.00', $final->raw_actual_hours);
        $this->assertSame('8.00', $final->actual_hours);
    }

    public function test_gap_tl_recommend_and_admin_decide(): void
    {
        $leader = User::factory()->teamLeader()->create();
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee()->create(['team_leader_id' => $leader->id]);

        $service = app(AttendanceService::class);
        $service->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-08-03 10:00'));
        $service->clockOut($employee, Carbon::parse('2026-08-03 11:00'));
        $service->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-08-03 12:00'));

        $gap = AttendanceGap::query()->firstOrFail();
        $gaps = app(AttendanceGapService::class);

        $gaps->tlRecommend($leader, $gap, AttendanceGap::TL_APPROVE, 'Approved break');
        $this->assertSame(AttendanceGap::TL_APPROVE, $gap->fresh()->tl_recommendation);

        $gaps->adminDecide($manager, $gap->fresh(), AttendanceGap::ADMIN_APPROVED, 'OK');
        $this->assertSame(AttendanceGap::ADMIN_APPROVED, $gap->fresh()->admin_decision);
    }

    public function test_tl_can_set_credited_hours_and_late_override(): void
    {
        $leader = User::factory()->teamLeader()->create();
        $employee = User::factory()->employee()->create(['team_leader_id' => $leader->id]);

        $service = app(AttendanceService::class);
        $service->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-08-03 10:15'));
        $attendance = $service->clockOut($employee, Carbon::parse('2026-08-03 18:00'));

        $updated = app(AttendanceTimeReviewService::class)->applyAdjustments($leader, $attendance, [
            'credited_hours' => 8,
            'adjusted_late_minutes' => 5,
        ]);

        $this->assertSame('8.00', $updated->actual_hours);
        $this->assertSame(5, $updated->late_minutes);
    }

    public function test_tl_attendance_history_shows_time_comparison(): void
    {
        $leader = User::factory()->teamLeader()->create();
        $employee = User::factory()->employee()->create([
            'team_leader_id' => $leader->id,
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
        ]);

        $service = app(AttendanceService::class);
        $service->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-08-03 10:15'));
        $attendance = $service->clockOut($employee, Carbon::parse('2026-08-03 18:00'));

        app(AttendanceTimeReviewService::class)->applyAdjustments($leader, $attendance, [
            'credited_hours' => 8,
            'adjusted_late_minutes' => 5,
        ]);

        $this->actingAs($leader)
            ->get(route('team-leader.attendance', [
                'from' => '2026-08-03',
                'to' => '2026-08-03',
                'kind' => 'all',
                'evidence' => 'all',
            ]))
            ->assertOk()
            ->assertSee(__('attendance_review.comparison.system'), false)
            ->assertSee(__('attendance_review.comparison.credited'), false)
            ->assertSee(__('attendance_review.comparison.overridden'), false);
    }

    public function test_tl_can_reset_to_system_recommended_time(): void
    {
        $leader = User::factory()->teamLeader()->create();
        $employee = User::factory()->employee()->create([
            'team_leader_id' => $leader->id,
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
        ]);

        $service = app(AttendanceService::class);
        $service->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-08-03 10:15'));
        $attendance = $service->clockOut($employee, Carbon::parse('2026-08-03 18:15'));

        app(AttendanceTimeReviewService::class)->applyAdjustments($leader, $attendance, [
            'credited_hours' => 7,
            'adjusted_late_minutes' => 0,
        ]);

        $this->actingAs($leader)
            ->post(route('team-leader.attendance.time', $attendance->fresh()), [
                'reset_system' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', __('attendance_review.flash.reset'));

        $fresh = $attendance->fresh();
        $this->assertNull($fresh->credited_hours);
        $this->assertNull($fresh->adjusted_late_minutes);
        $this->assertSame(15, $fresh->late_minutes);
        $this->assertSame('8.00', $fresh->actual_hours);
    }

    public function test_driver_can_multi_punch_same_shift(): void
    {
        $driver = User::factory()->driver()->create([
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
        ]);

        $service = app(AttendanceService::class);
        $service->clockIn($driver, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-08-03 10:00'));
        $service->clockOut($driver, Carbon::parse('2026-08-03 11:00'));
        $attendance = $service->clockIn($driver, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-08-03 12:00'));
        $final = $service->clockOut($driver, Carbon::parse('2026-08-03 18:00'));

        $this->assertSame(2, $attendance->segments()->count());
        $this->assertSame('7.00', $final->actual_hours);
    }
}
