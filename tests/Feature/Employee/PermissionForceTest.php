<?php

namespace Tests\Feature\Employee;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Gate 2.7 / T-2.9 — REJECTED → FORCED (employee self, TL team, or Manager); double penalty.
 */
class PermissionForceTest extends TestCase
{
    use RefreshDatabase;

    private User $leader;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->leader = User::factory()->teamLeader()->create();
        $this->employee = User::factory()->employee()->create([
            'team_leader_id' => $this->leader->id,
            'currency' => 'EGP',
            'language_preference' => 'en',
        ]);
    }

    private function rejectedPermission(array $overrides = []): Permission
    {
        return Permission::factory()->create(array_merge([
            'user_id' => $this->employee->id,
            'team_leader_id' => $this->leader->id,
            'type' => Permission::TYPE_LEAVE,
            'request_date' => '2026-08-05',
            'from_time' => '14:00',
            'to_time' => '16:00', // 120 min × 1 EGP = 120
            'status' => Permission::STATUS_REJECTED,
            'penalty_amount' => 120,
            'is_double_penalty' => false,
        ], $overrides));
    }

    public function test_employee_sees_force_button_on_rejected_request(): void
    {
        app()->setLocale('en');

        $this->rejectedPermission();

        $this->actingAs($this->employee)
            ->get(route('employee.requests'))
            ->assertOk()
            ->assertSee(__('permissions.force'), false)
            ->assertSee(__('permissions.statuses.REJECTED'), false);
    }

    public function test_team_leader_sees_force_on_approvals(): void
    {
        app()->setLocale('en');

        $this->rejectedPermission();

        $this->actingAs($this->leader)
            ->get(route('team-leader.approvals', ['filter' => 'decided']))
            ->assertOk()
            ->assertSee(__('permissions.force'), false);
    }

    public function test_employee_can_force_own_rejected_permission(): void
    {
        $row = $this->rejectedPermission();

        $this->actingAs($this->employee)
            ->post(route('employee.requests.permissions.force', $row))
            ->assertRedirect(route('employee.requests', ['tab' => 'permissions']))
            ->assertSessionHas('success');

        $row->refresh();
        $this->assertSame(Permission::STATUS_FORCED, $row->status);
        $this->assertSame($this->employee->id, $row->decided_by);
        $this->assertTrue($row->is_double_penalty);
        $this->assertSame('240.00', (string) $row->penalty_amount);
    }

    public function test_team_leader_can_force_team_member_rejected(): void
    {
        $row = $this->rejectedPermission();

        $this->actingAs($this->leader)
            ->post(route('team-leader.approvals.permissions.force', $row))
            ->assertRedirect(route('team-leader.approvals', ['filter' => 'decided']))
            ->assertSessionHas('success');

        $row->refresh();
        $this->assertSame(Permission::STATUS_FORCED, $row->status);
        $this->assertSame($this->leader->id, $row->decided_by);
        $this->assertTrue($row->is_double_penalty);
        $this->assertSame('240.00', (string) $row->penalty_amount);
    }

    public function test_team_leader_cannot_force_other_team(): void
    {
        $otherLeader = User::factory()->teamLeader()->create();
        $otherEmp = User::factory()->employee()->create(['team_leader_id' => $otherLeader->id]);
        $row = $this->rejectedPermission([
            'user_id' => $otherEmp->id,
            'team_leader_id' => $otherLeader->id,
        ]);

        $this->actingAs($this->leader)
            ->post(route('team-leader.approvals.permissions.force', $row))
            ->assertForbidden();

        $this->assertSame(Permission::STATUS_REJECTED, $row->fresh()->status);
    }

    public function test_employee_cannot_force_pending_or_accepted(): void
    {
        $pending = $this->rejectedPermission(['status' => Permission::STATUS_PENDING, 'penalty_amount' => 0]);

        $this->actingAs($this->employee)
            ->from(route('employee.requests'))
            ->post(route('employee.requests.permissions.force', $pending))
            ->assertRedirect(route('employee.requests'))
            ->assertSessionHasErrors('permission');
    }

    public function test_manager_can_force_any_rejected(): void
    {
        $row = $this->rejectedPermission();
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)
            ->post(route('team-leader.approvals.permissions.force', $row))
            ->assertRedirect(route('team-leader.approvals', ['filter' => 'decided']));

        $this->assertSame(Permission::STATUS_FORCED, $row->fresh()->status);
        $this->assertSame($manager->id, $row->fresh()->decided_by);
        $this->assertTrue($row->fresh()->is_double_penalty);
    }

    public function test_employee_cannot_force_another_employees_permission(): void
    {
        $other = User::factory()->employee()->create([
            'team_leader_id' => $this->leader->id,
        ]);
        $row = $this->rejectedPermission(['user_id' => $other->id]);

        $this->actingAs($this->employee)
            ->post(route('employee.requests.permissions.force', $row))
            ->assertForbidden();
    }
}
