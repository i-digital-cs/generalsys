<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Permission;
use App\Models\User;
use App\Services\Attendance\AttendanceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TL + Manager self-service: clock, daily report, My Requests;
 * Manager decides TL permissions and replies to TL reports.
 */
class StaffSelfServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $leader;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-03 12:00:00');

        $this->manager = User::factory()->manager()->create([
            'language_preference' => 'en',
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
            'shift_type_default' => Attendance::SHIFT_FULL_TIME,
            'name_en' => 'System Admin',
        ]);
        $this->leader = User::factory()->teamLeader()->create([
            'language_preference' => 'en',
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
            'shift_type_default' => Attendance::SHIFT_FULL_TIME,
            'name_en' => 'Team Leader',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_team_leader_can_submit_permission_and_manager_decides(): void
    {
        $this->actingAs($this->leader)
            ->post(route('employee.requests.permissions.store'), [
                'type' => Permission::TYPE_LEAVE,
                'request_date' => '2026-08-05',
                'from_time' => '14:00',
                'to_time' => '16:00',
                'reason' => 'TL leave',
            ])
            ->assertRedirect(route('employee.requests', ['tab' => 'permissions']));

        $row = Permission::query()->where('user_id', $this->leader->id)->first();
        $this->assertNotNull($row);
        $this->assertNull($row->team_leader_id);
        $this->assertSame(Permission::STATUS_PENDING, $row->status);

        $this->actingAs($this->manager)
            ->withSession(['locale' => 'en'])
            ->get(route('team-leader.approvals', ['filter' => 'pending']))
            ->assertOk()
            ->assertSee('Team Leader', false)
            ->assertSee(__('permissions.types.LEAVE_PERMISSION'), false);

        $this->actingAs($this->manager)
            ->post(route('team-leader.approvals.permissions.approve', $row))
            ->assertRedirect();

        $this->assertSame(Permission::STATUS_ACCEPTED, $row->fresh()->status);
    }

    public function test_team_leader_cannot_see_own_permission_in_team_approvals(): void
    {
        Permission::factory()->create([
            'user_id' => $this->leader->id,
            'team_leader_id' => null,
            'type' => Permission::TYPE_LEAVE,
            'request_date' => '2026-08-05',
            'from_time' => '14:00',
            'to_time' => '16:00',
            'status' => Permission::STATUS_PENDING,
        ]);

        $this->actingAs($this->leader)
            ->get(route('team-leader.approvals', ['filter' => 'pending']))
            ->assertOk()
            ->assertDontSee(__('permissions.types.LEAVE_PERMISSION'), false);
    }

    public function test_manager_can_submit_own_permission(): void
    {
        $this->actingAs($this->manager)
            ->post(route('employee.requests.permissions.store'), [
                'type' => Permission::TYPE_LATE,
                'request_date' => '2026-08-06',
                'from_time' => '10:00',
                'to_time' => '10:30',
            ])
            ->assertRedirect(route('employee.requests', ['tab' => 'permissions']));

        $this->assertDatabaseHas('permissions', [
            'user_id' => $this->manager->id,
            'type' => Permission::TYPE_LATE,
            'status' => Permission::STATUS_PENDING,
        ]);
    }

    public function test_team_leader_report_appears_for_manager_not_self_queue(): void
    {
        $service = app(AttendanceService::class);
        $service->clockIn($this->leader, Attendance::SHIFT_FULL_TIME);
        Carbon::setTestNow('2026-08-03 18:00:00');
        $service->clockOut($this->leader);

        $this->actingAs($this->leader)
            ->post(route('employee.report.store'), [
                'date' => '2026-08-03',
                'daily_report' => 'Led the team today.',
            ])
            ->assertRedirect();

        $this->actingAs($this->leader)
            ->get(route('team-leader.reports', ['filter' => 'pending']))
            ->assertOk()
            ->assertDontSee('Led the team today.', false);

        $this->actingAs($this->manager)
            ->withSession(['locale' => 'en'])
            ->get(route('team-leader.reports', ['filter' => 'pending']))
            ->assertOk()
            ->assertSee('Team Leader', false)
            ->assertSee('Led the team today.', false);

        $attendance = Attendance::query()
            ->where('user_id', $this->leader->id)
            ->whereDate('date', '2026-08-03')
            ->orderBy('clock_in')
            ->first();

        $this->actingAs($this->manager)
            ->post(route('team-leader.reports.respond', $attendance), [
                'team_leader_response' => 'Thanks for the update.',
            ])
            ->assertRedirect();

        $this->assertSame('Thanks for the update.', $attendance->fresh()->team_leader_response);
    }

    public function test_team_leader_can_force_own_rejected_permission(): void
    {
        $row = Permission::factory()->create([
            'user_id' => $this->leader->id,
            'team_leader_id' => null,
            'type' => Permission::TYPE_LEAVE,
            'request_date' => '2026-08-05',
            'from_time' => '14:00',
            'to_time' => '16:00',
            'status' => Permission::STATUS_REJECTED,
            'penalty_amount' => 120,
            'is_double_penalty' => false,
        ]);

        $this->actingAs($this->leader)
            ->post(route('employee.requests.permissions.force', $row))
            ->assertRedirect(route('employee.requests', ['tab' => 'permissions']));

        $this->assertSame(Permission::STATUS_FORCED, $row->fresh()->status);
        $this->assertTrue($row->fresh()->is_double_penalty);
    }
}
