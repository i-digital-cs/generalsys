<?php

namespace Tests\Feature\TeamLeader;

use App\Models\Permission;
use App\Models\User;
use App\Services\Employees\TeamMembershipService;
use App\Services\Permissions\PermissionPenaltyCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Gate 2.6 / T-2.8 — TL accept/reject permissions; penalty on reject; no money in UI.
 */
class PermissionApprovalTest extends TestCase
{
    use RefreshDatabase;

    private User $leader;

    private User $employee;

    private User $otherEmployee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->leader = User::factory()->teamLeader()->create([
            'language_preference' => 'en',
        ]);
        $this->employee = User::factory()->employee()->create([
            'team_leader_id' => $this->leader->id,
            'currency' => 'EGP',
            'name_en' => 'Team Member One',
        ]);
        $otherLeader = User::factory()->teamLeader()->create();
        $this->otherEmployee = User::factory()->employee()->create([
            'team_leader_id' => $otherLeader->id,
            'name_en' => 'Other Team Person',
        ]);
    }

    private function pendingPermission(User $user, array $overrides = []): Permission
    {
        return Permission::factory()->create(array_merge([
            'user_id' => $user->id,
            'team_leader_id' => $user->team_leader_id,
            'type' => Permission::TYPE_LEAVE,
            'request_date' => '2026-08-05',
            'from_time' => '14:00',
            'to_time' => '16:00',
            'status' => Permission::STATUS_PENDING,
            'penalty_amount' => 0,
        ], $overrides));
    }

    public function test_team_leader_sees_pending_team_only(): void
    {
        app()->setLocale('en');

        $this->pendingPermission($this->employee);
        $this->pendingPermission($this->otherEmployee);

        $this->actingAs($this->leader)
            ->get(route('team-leader.approvals'))
            ->assertOk()
            ->assertSee(__('permissions.tl.title'), false)
            ->assertSee('Team Member One', false)
            ->assertDontSee('Other Team Person', false)
            ->assertSee(__('permissions.tl.accept'), false)
            ->assertSee(__('permissions.tl.reject'), false)
            ->assertDontSee('120.00', false)
            ->assertDontSee('penalty_amount', false);
    }

    public function test_accept_sets_accepted_with_zero_penalty(): void
    {
        $row = $this->pendingPermission($this->employee);

        $this->actingAs($this->leader)
            ->post(route('team-leader.approvals.permissions.approve', $row))
            ->assertRedirect(route('team-leader.approvals', ['filter' => 'pending']))
            ->assertSessionHas('success');

        $row->refresh();
        $this->assertSame(Permission::STATUS_ACCEPTED, $row->status);
        $this->assertSame($this->leader->id, $row->decided_by);
        $this->assertSame('0.00', (string) $row->penalty_amount);
        $this->assertFalse($row->is_double_penalty);
    }

    public function test_reject_sets_rejected_and_computes_penalty(): void
    {
        $row = $this->pendingPermission($this->employee, [
            'from_time' => '14:00',
            'to_time' => '16:00', // 120 minutes
        ]);

        $expected = app(PermissionPenaltyCalculator::class)->amountForRejection($row);

        $this->actingAs($this->leader)
            ->post(route('team-leader.approvals.permissions.reject', $row))
            ->assertRedirect(route('team-leader.approvals', ['filter' => 'pending']));

        $row->refresh();
        $this->assertSame(Permission::STATUS_REJECTED, $row->status);
        $this->assertSame($this->leader->id, $row->decided_by);
        $this->assertSame(number_format($expected, 2, '.', ''), (string) $row->penalty_amount);
        $this->assertFalse($row->is_double_penalty);
        $this->assertSame(120.0, (float) $expected); // 120 min × 1.00 EGP
    }

    public function test_cannot_approve_other_team_request(): void
    {
        $row = $this->pendingPermission($this->otherEmployee);

        $this->actingAs($this->leader)
            ->post(route('team-leader.approvals.permissions.approve', $row))
            ->assertForbidden();
    }

    public function test_employee_cannot_open_approvals(): void
    {
        $this->actingAs($this->employee)
            ->get(route('team-leader.approvals'))
            ->assertForbidden();
    }

    public function test_employee_sees_updated_status_after_accept(): void
    {
        app()->setLocale('en');

        $row = $this->pendingPermission($this->employee);
        $this->actingAs($this->leader)
            ->post(route('team-leader.approvals.permissions.approve', $row));

        $this->actingAs($this->employee)
            ->get(route('employee.requests'))
            ->assertOk()
            ->assertSee(__('permissions.statuses.ACCEPTED'), false);
    }

    public function test_manager_can_reject_any_pending(): void
    {
        $row = $this->pendingPermission($this->otherEmployee);
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)
            ->post(route('team-leader.approvals.permissions.reject', $row))
            ->assertRedirect(route('team-leader.approvals', ['filter' => 'pending']));

        $this->assertSame(Permission::STATUS_REJECTED, $row->fresh()->status);
        $this->assertSame($manager->id, $row->fresh()->decided_by);
    }

    public function test_either_team_leader_sees_and_can_decide_the_same_member(): void
    {
        app()->setLocale('en');

        $leaderA = User::factory()->teamLeader()->create(['name_en' => 'Alpha Dual TL']);
        $leaderB = User::factory()->teamLeader()->create(['name_en' => 'Beta Dual TL']);
        $leaderC = User::factory()->teamLeader()->create(['name_en' => 'Gamma Dual TL']);
        $member = User::factory()->employee()->create([
            'name_en' => 'Dual Approval Member',
            'currency' => 'EGP',
        ]);
        app(TeamMembershipService::class)->syncLeaders($member, [
            $leaderA->id,
            $leaderB->id,
        ]);

        $row = $this->pendingPermission($member, ['team_leader_id' => $leaderA->id]);

        $this->actingAs($leaderA)
            ->get(route('team-leader.approvals'))
            ->assertOk()
            ->assertSee('Dual Approval Member', false)
            ->assertSee(__('permissions.fields.submitted_for_team'), false)
            ->assertSee('Alpha Dual TL', false)
            ->assertSee('Beta Dual TL', false);

        $this->actingAs($leaderB)
            ->get(route('team-leader.approvals'))
            ->assertOk()
            ->assertSee('Dual Approval Member', false);

        $this->actingAs($leaderC)
            ->get(route('team-leader.approvals'))
            ->assertOk()
            ->assertDontSee('Dual Approval Member', false);

        $this->actingAs($leaderC)
            ->post(route('team-leader.approvals.permissions.approve', $row))
            ->assertForbidden();

        $this->actingAs($leaderB)
            ->post(route('team-leader.approvals.permissions.approve', $row))
            ->assertRedirect(route('team-leader.approvals', ['filter' => 'pending']));

        $row->refresh();
        $this->assertSame(Permission::STATUS_ACCEPTED, $row->status);
        $this->assertSame($leaderB->id, $row->decided_by);

        $this->actingAs($leaderA)
            ->get(route('team-leader.approvals', ['filter' => 'decided']))
            ->assertOk()
            ->assertSee(__('permissions.fields.decided_by'), false)
            ->assertSee('Beta Dual TL', false)
            ->assertDontSee('120.00', false);

        $this->actingAs($member)
            ->get(route('employee.requests'))
            ->assertOk()
            ->assertSee(__('permissions.fields.decided_by'), false)
            ->assertSee('Beta Dual TL', false);
    }
}
