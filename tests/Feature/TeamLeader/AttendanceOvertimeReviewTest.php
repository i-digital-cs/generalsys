<?php

namespace Tests\Feature\TeamLeader;

use App\Models\Attendance;
use App\Models\User;
use App\Notifications\AttendanceOvertimeNotification;
use App\Services\Attendance\AttendanceService;
use App\Services\Attendance\AttendanceTimeCalculator;
use App\Services\Attendance\AttendanceTimeReviewService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Tests\TestCase;

/**
 * R8 — overtime TL recommend → Admin/Assistant final + notifications.
 */
class AttendanceOvertimeReviewTest extends TestCase
{
    use RefreshDatabase;

    private User $leader;

    private User $otherLeader;

    private User $manager;

    private User $assistant;

    private User $employee;

    private User $outsider;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('en');

        $this->leader = User::factory()->teamLeader()->create();
        $this->otherLeader = User::factory()->teamLeader()->create();
        $this->manager = User::factory()->manager()->create();
        $this->assistant = User::factory()->assistant()->create();
        $this->employee = User::factory()->employee()->create([
            'team_leader_id' => $this->leader->id,
            'name_en' => 'OT Member',
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
        ]);
        $this->outsider = User::factory()->employee()->create([
            'team_leader_id' => $this->otherLeader->id,
            'name_en' => 'OT Outsider',
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
        ]);
    }

    private function createOvertimeAttendanceFor(User $employee): Attendance
    {
        $service = app(AttendanceService::class);
        $service->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-08-03 10:00'));

        return $service->clockOut($employee, Carbon::parse('2026-08-03 19:00'));
    }

    public function test_team_leader_sees_pending_overtime_for_team_only(): void
    {
        $this->createOvertimeAttendanceFor($this->employee);
        $this->createOvertimeAttendanceFor($this->outsider);

        $this->actingAs($this->leader)
            ->get(route('team-leader.approvals'))
            ->assertOk()
            ->assertSee(__('attendance_review.overtime.section'), false)
            ->assertSee('OT Member', false)
            ->assertDontSee('OT Outsider', false)
            ->assertSee(__('attendance_review.recommend_overtime'), false);
    }

    public function test_tl_recommend_prefills_system_suggested_minutes(): void
    {
        NotificationFacade::fake();

        $attendance = $this->createOvertimeAttendanceFor($this->employee);

        $this->actingAs($this->leader)
            ->post(route('team-leader.attendance.overtime.tl', $attendance), [
                'recommended_overtime_minutes' => 60,
                'overtime_tl_recommendation' => 'RECOMMEND',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', __('attendance_review.flash.overtime_tl'));

        $fresh = $attendance->fresh();
        $this->assertSame('RECOMMEND', $fresh->overtime_tl_recommendation);
        $this->assertSame(60, $fresh->recommended_overtime_minutes);
        $this->assertSame('PENDING', $fresh->overtime_admin_decision);

        NotificationFacade::assertSentTo($this->manager, AttendanceOvertimeNotification::class);
        NotificationFacade::assertSentTo($this->employee, AttendanceOvertimeNotification::class);
    }

    public function test_manager_can_approve_overtime_and_increase_credited_hours(): void
    {
        NotificationFacade::fake();

        $attendance = $this->createOvertimeAttendanceFor($this->employee);

        app(AttendanceTimeReviewService::class)->tlRecommendOvertime($this->leader, $attendance, 60, 'RECOMMEND');

        $this->actingAs($this->manager)
            ->get(route('team-leader.approvals'))
            ->assertOk()
            ->assertSee(__('attendance_review.overtime.pending_admin'), false)
            ->assertSee(__('attendance_review.overtime.approve'), false);

        $this->actingAs($this->manager)
            ->post(route('team-leader.attendance.overtime.admin', $attendance->fresh()), [
                'overtime_admin_decision' => 'APPROVED',
                'filter' => 'pending',
            ])
            ->assertRedirect(route('team-leader.approvals', ['filter' => 'pending']))
            ->assertSessionHas('success', __('attendance_review.flash.overtime_admin'));

        $fresh = $attendance->fresh();
        $this->assertSame('APPROVED', $fresh->overtime_admin_decision);
        $this->assertSame('9.00', $fresh->actual_hours);

        NotificationFacade::assertSentTo($this->employee, AttendanceOvertimeNotification::class);
    }

    public function test_assistant_can_finalize_overtime(): void
    {
        $attendance = $this->createOvertimeAttendanceFor($this->employee);
        app(AttendanceTimeReviewService::class)->tlRecommendOvertime($this->leader, $attendance, 30, 'RECOMMEND');

        $this->actingAs($this->assistant)
            ->get(route('team-leader.approvals'))
            ->assertOk()
            ->assertSee(__('attendance_review.overtime.section'), false)
            ->assertSee(__('attendance_review.overtime.approve'), false);

        $this->actingAs($this->assistant)
            ->post(route('team-leader.attendance.overtime.admin', $attendance->fresh()), [
                'overtime_admin_decision' => 'APPROVED',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', __('attendance_review.flash.overtime_admin'));

        $this->assertSame('APPROVED', $attendance->fresh()->overtime_admin_decision);
    }

    public function test_tl_no_overtime_skips_admin_queue(): void
    {
        $attendance = $this->createOvertimeAttendanceFor($this->employee);

        app(AttendanceTimeReviewService::class)->tlRecommendOvertime($this->leader, $attendance, 0, 'NO_OVERTIME');

        $this->actingAs($this->manager)
            ->get(route('team-leader.approvals', ['filter' => 'pending']))
            ->assertOk()
            ->assertSee(__('attendance_review.overtime.empty_pending_admin'), false);
    }

    public function test_summarize_exposes_credited_cap_after_approval(): void
    {
        $attendance = $this->createOvertimeAttendanceFor($this->employee);
        $reviews = app(AttendanceTimeReviewService::class);
        $reviews->tlRecommendOvertime($this->leader, $attendance, 60, 'RECOMMEND');
        $reviews->adminDecideOvertime($this->manager, $attendance->fresh(), 'APPROVED');

        $summary = app(AttendanceTimeCalculator::class)->summarize(
            $attendance->fresh(['segments', 'gaps']),
            app(AttendanceService::class),
        );

        $this->assertSame(60, $summary->approvedOvertimeMinutes);
        $this->assertEqualsWithDelta(9.0, $summary->creditedCapHours, 0.01);
    }
}
