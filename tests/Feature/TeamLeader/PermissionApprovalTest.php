<?php

namespace Tests\Feature\TeamLeader;

use App\Models\Permission;
use App\Models\User;
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
    }
}
