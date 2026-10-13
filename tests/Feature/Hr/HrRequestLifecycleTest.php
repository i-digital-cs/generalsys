<?php

namespace Tests\Feature\Hr;

use App\Exceptions\PermissionRequestException;
use App\Exceptions\VacationRequestException;
use App\Models\Permission;
use App\Models\User;
use App\Models\Vacation;
use App\Models\VacationBalance;
use App\Services\Permissions\PermissionRequestService;
use App\Services\Vacations\VacationRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Phase 4 — HR leave/vacation lifecycle, policies, and transition immutability.
 */
class HrRequestLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $leader;

    private User $employee;

    private User $otherLeader;

    private User $otherEmployee;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->leader = User::factory()->teamLeader()->create();
        $this->employee = User::factory()->employee()->create([
            'team_leader_id' => $this->leader->id,
            'currency' => 'EGP',
        ]);
        $this->otherLeader = User::factory()->teamLeader()->create();
        $this->otherEmployee = User::factory()->employee()->create([
            'team_leader_id' => $this->otherLeader->id,
        ]);
        $this->manager = User::factory()->manager()->create();
    }

    private function pendingLeave(): Permission
    {
        return Permission::factory()->create([
            'user_id' => $this->employee->id,
            'team_leader_id' => $this->leader->id,
            'type' => Permission::TYPE_LEAVE,
            'request_date' => '2026-08-05',
            'from_time' => '14:00',
            'to_time' => '16:00',
            'status' => Permission::STATUS_PENDING,
            'penalty_amount' => 0,
        ]);
    }

    private function pendingAnnual(float $days = 2.0): Vacation
    {
        VacationBalance::query()->create([
            'user_id' => $this->employee->id,
            'year' => 2026,
            'accrued_days' => 21,
            'used_days' => 0,
            'pending_days' => $days,
            'remaining_days' => 21 - $days,
        ]);

        return Vacation::factory()->create([
            'user_id' => $this->employee->id,
            'team_leader_id' => $this->leader->id,
            'type' => Vacation::TYPE_ANNUAL,
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-11',
            'days_requested' => $days,
            'status' => Vacation::STATUS_PENDING,
        ]);
    }

    public function test_permission_policy_mirrors_decide_and_force_rules(): void
    {
        $row = $this->pendingLeave();

        $this->assertTrue(Gate::forUser($this->leader)->allows('decide', $row));
        $this->assertTrue(Gate::forUser($this->manager)->allows('decide', $row));
        $this->assertFalse(Gate::forUser($this->otherLeader)->allows('decide', $row));
        $this->assertFalse(Gate::forUser($this->employee)->allows('decide', $row));

        $rejected = $this->pendingLeave();
        $rejected->update(['status' => Permission::STATUS_REJECTED, 'penalty_amount' => 120]);

        $this->assertTrue(Gate::forUser($this->employee)->allows('force', $rejected));
        $this->assertTrue(Gate::forUser($this->leader)->allows('force', $rejected));
        $this->assertFalse(Gate::forUser($this->otherLeader)->allows('force', $rejected));
        $this->assertFalse(Gate::forUser($this->otherEmployee)->allows('force', $rejected));
    }

    public function test_vacation_policy_mirrors_decide_and_force_rules(): void
    {
        $row = $this->pendingAnnual();

        $this->assertTrue(Gate::forUser($this->leader)->allows('decide', $row));
        $this->assertFalse(Gate::forUser($this->otherLeader)->allows('decide', $row));
        $this->assertFalse(Gate::forUser($this->employee)->allows('decide', $row));

        $row->update(['status' => Vacation::STATUS_REJECTED]);

        $this->assertTrue(Gate::forUser($this->employee)->allows('force', $row));
        $this->assertFalse(Gate::forUser($this->otherEmployee)->allows('force', $row));
    }

    public function test_repeated_permission_approve_is_rejected(): void
    {
        $row = $this->pendingLeave();
        $service = app(PermissionRequestService::class);

        $service->approve($this->leader, $row);

        $this->expectException(PermissionRequestException::class);
        $service->approve($this->leader, $row->fresh());
    }

    public function test_http_repeated_permission_approve_keeps_accepted_once(): void
    {
        $row = $this->pendingLeave();

        $this->actingAs($this->leader)
            ->post(route('team-leader.approvals.permissions.approve', $row))
            ->assertRedirect();

        $this->actingAs($this->leader)
            ->from(route('team-leader.approvals'))
            ->post(route('team-leader.approvals.permissions.approve', $row))
            ->assertRedirect(route('team-leader.approvals'))
            ->assertSessionHasErrors('permission');

        $this->assertSame(Permission::STATUS_ACCEPTED, $row->fresh()->status);
    }

    public function test_repeated_vacation_approve_does_not_double_confirm_balance(): void
    {
        $row = $this->pendingAnnual(2.0);
        $service = app(VacationRequestService::class);

        $service->approve($this->leader, $row);

        $balance = VacationBalance::query()
            ->where('user_id', $this->employee->id)
            ->where('year', 2026)
            ->firstOrFail();
        $this->assertSame(2.0, (float) $balance->used_days);
        $this->assertSame(0.0, (float) $balance->pending_days);

        try {
            $service->approve($this->leader, $row->fresh());
            $this->fail('Expected VacationRequestException for non-pending vacation');
        } catch (VacationRequestException) {
            // expected
        }

        $balance->refresh();
        $this->assertSame(2.0, (float) $balance->used_days);
        $this->assertSame(0.0, (float) $balance->pending_days);
        $this->assertSame(19.0, (float) $balance->remaining_days);
    }

    public function test_reject_then_force_vacation_charges_used_once(): void
    {
        $row = $this->pendingAnnual(3.0);
        $service = app(VacationRequestService::class);

        $service->reject($this->leader, $row);
        $balance = VacationBalance::query()
            ->where('user_id', $this->employee->id)
            ->where('year', 2026)
            ->firstOrFail();
        $this->assertSame(0.0, (float) $balance->pending_days);
        $this->assertSame(0.0, (float) $balance->used_days);
        $this->assertSame(21.0, (float) $balance->remaining_days);

        $service->force($this->employee, $row->fresh());

        $balance->refresh();
        $this->assertSame(3.0, (float) $balance->used_days);
        $this->assertSame(18.0, (float) $balance->remaining_days);

        try {
            $service->force($this->employee, $row->fresh());
            $this->fail('Expected VacationRequestException for non-rejected vacation');
        } catch (VacationRequestException) {
            // expected
        }

        $balance->refresh();
        $this->assertSame(3.0, (float) $balance->used_days);
    }

    public function test_unauthorized_permission_approve_via_policy_is_forbidden(): void
    {
        $row = $this->pendingLeave();

        $this->actingAs($this->otherLeader)
            ->post(route('team-leader.approvals.permissions.approve', $row))
            ->assertForbidden();

        $this->assertSame(Permission::STATUS_PENDING, $row->fresh()->status);
    }

    public function test_cross_employee_cannot_force_permission(): void
    {
        $row = $this->pendingLeave();
        $row->update(['status' => Permission::STATUS_REJECTED, 'penalty_amount' => 100]);

        $this->actingAs($this->otherEmployee)
            ->post(route('employee.requests.permissions.force', $row))
            ->assertForbidden();

        $this->assertSame(Permission::STATUS_REJECTED, $row->fresh()->status);
    }
}
