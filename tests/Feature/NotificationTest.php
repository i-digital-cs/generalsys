<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Permission;
use App\Models\RestDay;
use App\Models\User;
use App\Models\Vacation;
use App\Services\Attendance\AttendanceService;
use App\Services\Attendance\DailyReportService;
use App\Services\Attendance\MissingDailyReportService;
use App\Services\Employees\TeamMembershipService;
use App\Services\Permissions\PermissionRequestService;
use App\Services\Vacations\VacationRequestService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * In-app notifications: permission + daily report events; no money in payloads.
 */
class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $leader;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->leader = User::factory()->teamLeader()->create([
            'language_preference' => 'en',
        ]);
        $this->employee = User::factory()->employee()->create([
            'team_leader_id' => $this->leader->id,
            'currency' => 'EGP',
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
            'language_preference' => 'en',
        ]);
    }

    public function test_permission_submit_notifies_team_leader(): void
    {
        app(PermissionRequestService::class)->submit($this->employee, [
            'type' => Permission::TYPE_LEAVE,
            'request_date' => '2026-08-05',
            'from_time' => '14:00',
            'to_time' => '16:00',
        ]);

        $this->assertSame(1, $this->leader->fresh()->unreadNotifications()->count());
        $data = $this->leader->notifications()->first()->data;
        $this->assertSame('permission_submitted', $data['kind']);
        $this->assertArrayNotHasKey('penalty_amount', $data);
    }

    public function test_permission_reject_notifies_employee(): void
    {
        $permission = app(PermissionRequestService::class)->submit($this->employee, [
            'type' => Permission::TYPE_LEAVE,
            'request_date' => '2026-08-05',
            'from_time' => '14:00',
            'to_time' => '16:00',
        ]);

        $this->leader->notifications()->delete();

        app(PermissionRequestService::class)->reject($this->leader, $permission);

        $this->assertSame(1, $this->employee->fresh()->unreadNotifications()->count());
        $data = $this->employee->notifications()->first()->data;
        $this->assertSame('permission_decision', $data['kind']);
        $this->assertSame(Permission::STATUS_REJECTED, $data['status']);
        $this->assertArrayNotHasKey('penalty_amount', $data);
    }

    public function test_daily_report_submit_and_reply_notify_both_sides(): void
    {
        $service = app(AttendanceService::class);
        $service->clockIn($this->employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-08-03 10:00:00'));
        $service->clockOut($this->employee, Carbon::parse('2026-08-03 18:00:00'));

        $reports = app(DailyReportService::class);
        $row = $reports->submit($this->employee, '2026-08-03', 'Finished work');

        $this->assertSame(1, $this->leader->fresh()->unreadNotifications()->count());
        $this->assertSame('report_submitted', $this->leader->notifications()->first()->data['kind']);

        $this->leader->notifications()->delete();
        $reports->respond($this->leader, $row, 'Well done');

        $this->assertSame(1, $this->employee->fresh()->unreadNotifications()->count());
        $this->assertSame('report_replied', $this->employee->notifications()->first()->data['kind']);
    }

    public function test_manager_is_notified_on_permission_submit(): void
    {
        $manager = User::factory()->manager()->create();

        app(PermissionRequestService::class)->submit($this->employee, [
            'type' => Permission::TYPE_LEAVE,
            'request_date' => '2026-08-05',
            'from_time' => '14:00',
            'to_time' => '16:00',
        ]);

        $this->assertSame(1, $manager->fresh()->unreadNotifications()->count());
        $this->assertSame('permission_submitted', $manager->notifications()->first()->data['kind']);
    }

    public function test_approvals_all_filter_shows_history(): void
    {
        app()->setLocale('en');

        $accepted = Permission::factory()->create([
            'user_id' => $this->employee->id,
            'team_leader_id' => $this->leader->id,
            'status' => Permission::STATUS_ACCEPTED,
            'request_date' => '2026-08-01',
            'from_time' => '10:00',
            'to_time' => '11:00',
        ]);

        $this->actingAs($this->leader)
            ->get(route('team-leader.approvals', ['filter' => 'all']))
            ->assertOk()
            ->assertSee(__('permissions.tl.filters.all'), false)
            ->assertSee(__('permissions.statuses.ACCEPTED'), false);

        $this->actingAs(User::factory()->manager()->create())
            ->get(route('team-leader.approvals', ['filter' => 'all']))
            ->assertOk()
            ->assertSee(__('permissions.statuses.ACCEPTED'), false);
    }

    public function test_force_notifies_employee_leader_and_manager(): void
    {
        $manager = User::factory()->manager()->create();

        $permission = app(PermissionRequestService::class)->submit($this->employee, [
            'type' => Permission::TYPE_LEAVE,
            'request_date' => '2026-08-05',
            'from_time' => '14:00',
            'to_time' => '16:00',
        ]);

        app(PermissionRequestService::class)->reject($this->leader, $permission);

        $this->employee->notifications()->delete();
        $this->leader->notifications()->delete();
        $manager->notifications()->delete();

        // Manager forces → employee + TL notified (actor skipped).
        app(PermissionRequestService::class)->force($manager, $permission->fresh());

        $this->assertSame(1, $this->employee->fresh()->unreadNotifications()->count());
        $this->assertSame('permission_forced', $this->employee->notifications()->first()->data['kind']);
        $this->assertStringContainsString('/requests', $this->employee->notifications()->first()->data['url']);

        $this->assertSame(1, $this->leader->fresh()->unreadNotifications()->count());
        $this->assertSame('permission_forced', $this->leader->notifications()->first()->data['kind']);
        $this->assertStringContainsString('team-leader/approvals', $this->leader->notifications()->first()->data['url']);

        $this->assertSame(0, $manager->fresh()->unreadNotifications()->count());
    }

    public function test_team_leader_force_notifies_employee_and_manager_not_self(): void
    {
        $manager = User::factory()->manager()->create();

        $permission = app(PermissionRequestService::class)->submit($this->employee, [
            'type' => Permission::TYPE_LEAVE,
            'request_date' => '2026-08-05',
            'from_time' => '14:00',
            'to_time' => '16:00',
        ]);

        app(PermissionRequestService::class)->reject($this->leader, $permission);

        $this->employee->notifications()->delete();
        $this->leader->notifications()->delete();
        $manager->notifications()->delete();

        app(PermissionRequestService::class)->force($this->leader, $permission->fresh());

        $this->assertSame(1, $this->employee->fresh()->unreadNotifications()->count());
        $this->assertSame(0, $this->leader->fresh()->unreadNotifications()->count());
        $this->assertSame(1, $manager->fresh()->unreadNotifications()->count());
    }

    public function test_permission_submit_notifies_both_membership_leaders(): void
    {
        $leaderB = User::factory()->teamLeader()->create();
        $outsider = User::factory()->teamLeader()->create();
        app(TeamMembershipService::class)->syncLeaders($this->employee, [
            $this->leader->id,
            $leaderB->id,
        ]);

        app(PermissionRequestService::class)->submit($this->employee, [
            'type' => Permission::TYPE_LEAVE,
            'request_date' => '2026-08-05',
            'from_time' => '14:00',
            'to_time' => '16:00',
        ]);

        $this->assertSame(1, $this->leader->fresh()->unreadNotifications()->count());
        $this->assertSame(1, $leaderB->fresh()->unreadNotifications()->count());
        $this->assertSame(0, $outsider->fresh()->unreadNotifications()->count());
        $this->assertSame('permission_submitted', $leaderB->notifications()->first()->data['kind']);
    }

    public function test_permission_reject_notifies_the_other_team_leader(): void
    {
        $leaderB = User::factory()->teamLeader()->create();
        app(TeamMembershipService::class)->syncLeaders($this->employee, [
            $this->leader->id,
            $leaderB->id,
        ]);

        $permission = app(PermissionRequestService::class)->submit($this->employee, [
            'type' => Permission::TYPE_LEAVE,
            'request_date' => '2026-08-05',
            'from_time' => '14:00',
            'to_time' => '16:00',
        ]);

        $this->leader->notifications()->delete();
        $leaderB->notifications()->delete();
        $this->employee->notifications()->delete();

        app(PermissionRequestService::class)->reject($this->leader, $permission);

        $this->assertSame(0, $this->leader->fresh()->unreadNotifications()->count());
        $this->assertSame(1, $leaderB->fresh()->unreadNotifications()->count());
        $this->assertSame('permission_decision', $leaderB->notifications()->first()->data['kind']);
        $this->assertSame(1, $this->employee->fresh()->unreadNotifications()->count());
    }

    public function test_vacation_submit_notifies_both_membership_leaders(): void
    {
        $leaderB = User::factory()->teamLeader()->create();
        $outsider = User::factory()->teamLeader()->create();
        app(TeamMembershipService::class)->syncLeaders($this->employee, [
            $this->leader->id,
            $leaderB->id,
        ]);

        $rest = RestDay::factory()->create([
            'user_id' => $this->employee->id,
            'date' => '2026-08-07',
            'created_by' => $this->leader->id,
        ]);

        app(VacationRequestService::class)->submit($this->employee, [
            'type' => Vacation::TYPE_EMERGENCY,
            'start_date' => '2026-08-05',
            'replace_rest_day_id' => $rest->id,
        ]);

        $this->assertSame(1, $this->leader->fresh()->unreadNotifications()->count());
        $this->assertSame(1, $leaderB->fresh()->unreadNotifications()->count());
        $this->assertSame(0, $outsider->fresh()->unreadNotifications()->count());
        $this->assertSame('vacation_submitted', $this->leader->notifications()->first()->data['kind']);
    }

    public function test_daily_report_reply_notifies_the_other_team_leader(): void
    {
        $leaderB = User::factory()->teamLeader()->create();
        app(TeamMembershipService::class)->syncLeaders($this->employee, [
            $this->leader->id,
            $leaderB->id,
        ]);

        $service = app(AttendanceService::class);
        $service->clockIn($this->employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-08-03 10:00:00'));
        $service->clockOut($this->employee, Carbon::parse('2026-08-03 18:00:00'));

        $reports = app(DailyReportService::class);
        $row = $reports->submit($this->employee, '2026-08-03', 'Finished work');

        $this->assertSame(1, $this->leader->fresh()->unreadNotifications()->count());
        $this->assertSame(1, $leaderB->fresh()->unreadNotifications()->count());

        $this->leader->notifications()->delete();
        $leaderB->notifications()->delete();
        $this->employee->notifications()->delete();

        $reports->respond($this->leader, $row, 'Well done');

        $this->assertSame(0, $this->leader->fresh()->unreadNotifications()->count());
        $this->assertSame(1, $leaderB->fresh()->unreadNotifications()->count());
        $this->assertSame('report_replied', $leaderB->notifications()->first()->data['kind']);
        $this->assertSame(1, $this->employee->fresh()->unreadNotifications()->count());
    }

    public function test_missing_report_notifies_both_membership_leaders(): void
    {
        $leaderB = User::factory()->teamLeader()->create();
        $outsider = User::factory()->teamLeader()->create();
        app(TeamMembershipService::class)->syncLeaders($this->employee, [
            $this->leader->id,
            $leaderB->id,
        ]);

        $date = Carbon::parse('2026-08-10');
        app(MissingDailyReportService::class)->notifyForDate(
            $date,
            MissingDailyReportService::KIND_TODAY,
        );

        $kindsA = $this->leader->fresh()->notifications->pluck('data.kind');
        $kindsB = $leaderB->fresh()->notifications->pluck('data.kind');
        $this->assertTrue($kindsA->contains('report_missing_today'));
        $this->assertTrue($kindsB->contains('report_missing_today'));
        $this->assertSame(0, $outsider->fresh()->unreadNotifications()->count());
    }
}
