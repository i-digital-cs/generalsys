<?php

namespace Tests\Feature\TeamLeader;

use App\Models\Attendance;
use App\Models\AttendanceGap;
use App\Models\User;
use App\Notifications\AttendanceGapNotification;
use App\Services\Attendance\AttendanceService;
use App\Services\Employees\TeamMembershipService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Tests\TestCase;

/**
 * R7 — mid-shift break gap approval: TL recommend → Admin/Assistant final.
 */
class AttendanceGapApprovalTest extends TestCase
{
    use RefreshDatabase;

    private User $leader;

    private User $otherLeader;

    private User $manager;

    private User $employee;

    private User $outsider;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('en');

        $this->leader = User::factory()->teamLeader()->create();
        $this->otherLeader = User::factory()->teamLeader()->create();
        $this->manager = User::factory()->manager()->create();
        $this->employee = User::factory()->employee()->create([
            'team_leader_id' => $this->leader->id,
            'name_en' => 'Gap Member',
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
        ]);
        $this->outsider = User::factory()->employee()->create([
            'team_leader_id' => $this->otherLeader->id,
            'name_en' => 'Gap Outsider',
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
        ]);
    }

    private function createGapFor(User $employee): AttendanceGap
    {
        $service = app(AttendanceService::class);
        $service->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-08-03 10:00'));
        $service->clockOut($employee, Carbon::parse('2026-08-03 11:00'));
        $service->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-08-03 12:00'));

        return AttendanceGap::query()->firstOrFail();
    }

    public function test_team_leader_sees_pending_gap_for_team_only(): void
    {
        $this->createGapFor($this->employee);
        $this->createGapFor($this->outsider);

        $this->actingAs($this->leader)
            ->get(route('team-leader.approvals'))
            ->assertOk()
            ->assertSee(__('attendance_gaps.section'), false)
            ->assertSee('Gap Member', false)
            ->assertDontSee('Gap Outsider', false)
            ->assertSee(__('attendance_gaps.recommend_approve'), false);
    }

    public function test_either_team_leader_can_recommend(): void
    {
        app(TeamMembershipService::class)->syncLeaders($this->employee, [
            $this->leader->id,
            $this->otherLeader->id,
        ]);

        $gap = $this->createGapFor($this->employee);

        $this->actingAs($this->otherLeader)
            ->post(route('team-leader.approvals.gaps.recommend', $gap), [
                'recommendation' => 'APPROVE',
                'note' => 'OK break',
                'filter' => 'pending',
            ])
            ->assertRedirect(route('team-leader.approvals', ['filter' => 'pending']))
            ->assertSessionHas('success', __('attendance_gaps.flash.tl_recommend'));

        $this->assertSame(AttendanceGap::TL_APPROVE, $gap->fresh()->tl_recommendation);
        $this->assertSame('OK break', $gap->fresh()->tl_note);
    }

    public function test_manager_can_finalize_gap_after_tl_recommendation(): void
    {
        $gap = $this->createGapFor($this->employee);

        $this->actingAs($this->leader)
            ->post(route('team-leader.approvals.gaps.recommend', $gap), [
                'recommendation' => 'APPROVE',
            ]);

        $this->actingAs($this->manager)
            ->get(route('team-leader.approvals'))
            ->assertOk()
            ->assertSee('Gap Member', false)
            ->assertSee(__('attendance_gaps.approve'), false);

        $this->actingAs($this->manager)
            ->post(route('team-leader.approvals.gaps.decide', $gap->fresh()), [
                'decision' => 'APPROVED',
                'note' => 'Approved',
                'filter' => 'pending',
            ])
            ->assertRedirect(route('team-leader.approvals', ['filter' => 'pending']))
            ->assertSessionHas('success', __('attendance_gaps.flash.admin_decide'));

        $fresh = $gap->fresh();
        $this->assertSame(AttendanceGap::ADMIN_APPROVED, $fresh->admin_decision);
        $this->assertSame('Approved', $fresh->admin_note);
    }

    public function test_assistant_can_finalize_gap_after_tl_recommendation(): void
    {
        $assistant = User::factory()->assistant()->create();
        $gap = $this->createGapFor($this->employee);

        $this->actingAs($this->leader)
            ->post(route('team-leader.approvals.gaps.recommend', $gap), [
                'recommendation' => 'APPROVE',
            ]);

        $this->actingAs($assistant)
            ->get(route('team-leader.approvals'))
            ->assertOk()
            ->assertSee('Gap Member', false)
            ->assertSee(__('attendance_gaps.approve'), false)
            ->assertDontSee(__('permissions.tl.section_permissions'), false);

        $this->actingAs($assistant)
            ->post(route('team-leader.approvals.gaps.decide', $gap->fresh()), [
                'decision' => 'APPROVED',
                'note' => 'Assistant OK',
                'filter' => 'pending',
            ])
            ->assertRedirect(route('team-leader.approvals', ['filter' => 'pending']))
            ->assertSessionHas('success', __('attendance_gaps.flash.admin_decide'));

        $fresh = $gap->fresh();
        $this->assertSame(AttendanceGap::ADMIN_APPROVED, $fresh->admin_decision);
        $this->assertSame((int) $assistant->id, (int) $fresh->admin_decided_by);
        $this->assertSame('Assistant OK', $fresh->admin_note);
    }

    public function test_decided_filter_shows_finalized_gaps(): void
    {
        $gap = $this->createGapFor($this->employee);

        $this->actingAs($this->leader)
            ->post(route('team-leader.approvals.gaps.recommend', $gap), ['recommendation' => 'APPROVE']);

        $this->actingAs($this->manager)
            ->post(route('team-leader.approvals.gaps.decide', $gap->fresh()), ['decision' => 'APPROVED']);

        $this->actingAs($this->leader)
            ->get(route('team-leader.approvals', ['filter' => 'decided']))
            ->assertOk()
            ->assertSee('Gap Member', false)
            ->assertSee(__('attendance_gaps.status.APPROVED'), false);

        $this->actingAs($this->leader)
            ->get(route('team-leader.approvals', ['filter' => 'pending']))
            ->assertOk()
            ->assertDontSee(__('attendance_gaps.recommend_approve'), false);
    }

    public function test_gap_pending_notifies_leaders_and_admins(): void
    {
        NotificationFacade::fake();

        $this->createGapFor($this->employee);

        NotificationFacade::assertSentTo($this->leader, AttendanceGapNotification::class);
        NotificationFacade::assertSentTo($this->manager, AttendanceGapNotification::class);
        NotificationFacade::assertSentTo($this->employee, AttendanceGapNotification::class);
    }

    public function test_admin_decision_notifies_employee_and_leaders(): void
    {
        NotificationFacade::fake();

        $gap = $this->createGapFor($this->employee);

        $this->actingAs($this->leader)
            ->post(route('team-leader.approvals.gaps.recommend', $gap), ['recommendation' => 'APPROVE']);

        NotificationFacade::fake();

        $this->actingAs($this->manager)
            ->post(route('team-leader.approvals.gaps.decide', $gap->fresh()), ['decision' => 'APPROVED']);

        NotificationFacade::assertSentTo($this->employee, AttendanceGapNotification::class);
        NotificationFacade::assertSentTo($this->leader, AttendanceGapNotification::class);
    }
}
