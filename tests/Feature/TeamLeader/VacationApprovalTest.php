<?php

namespace Tests\Feature\TeamLeader;

use App\Models\RestDay;
use App\Models\User;
use App\Models\Vacation;
use App\Models\VacationBalance;
use App\Services\Employees\TeamMembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T-2.11 — approve/reject vacation swap; rest day removed on approve.
 */
class VacationApprovalTest extends TestCase
{
    use RefreshDatabase;

    private User $leader;

    private User $employee;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->leader = User::factory()->teamLeader()->create([
            'language_preference' => 'en',
        ]);
        $this->employee = User::factory()->employee()->create([
            'team_leader_id' => $this->leader->id,
            'name_en' => 'Vacation Team Member',
        ]);
        $this->manager = User::factory()->manager()->create([
            'language_preference' => 'en',
        ]);
    }

    private function pendingWithRest(string $off = '2026-08-05', string $restDate = '2026-08-07'): array
    {
        $rest = RestDay::factory()->create([
            'user_id' => $this->employee->id,
            'date' => $restDate,
            'created_by' => $this->leader->id,
        ]);

        $vacation = Vacation::factory()->create([
            'user_id' => $this->employee->id,
            'team_leader_id' => $this->leader->id,
            'type' => Vacation::TYPE_EMERGENCY,
            'start_date' => $off,
            'end_date' => $off,
            'days_requested' => 1,
            'replace_rest_date' => $restDate,
            'replace_rest_day_id' => $rest->id,
            'status' => Vacation::STATUS_PENDING,
        ]);

        return [$vacation, $rest];
    }

    public function test_team_leader_sees_pending_vacation_with_replace_rest_date(): void
    {
        app()->setLocale('en');
        $this->pendingWithRest();

        $this->actingAs($this->leader)
            ->get(route('team-leader.approvals'))
            ->assertOk()
            ->assertSee(__('vacations.tl.section'), false)
            ->assertSee('Vacation Team Member', false)
            ->assertSee('2026-08-07', false)
            ->assertSee(__('vacations.tl.accept'), false);
    }

    public function test_approve_deletes_rest_day_and_keeps_replace_date(): void
    {
        [$row, $rest] = $this->pendingWithRest();

        $this->actingAs($this->leader)
            ->post(route('team-leader.approvals.vacations.approve', $row))
            ->assertRedirect()
            ->assertSessionHas('success');

        $row->refresh();
        $this->assertSame(Vacation::STATUS_APPROVED, $row->status);
        $this->assertSame($this->leader->id, $row->decided_by);
        $this->assertNull($row->replace_rest_day_id);
        $this->assertSame('2026-08-07', $row->replace_rest_date->toDateString());
        $this->assertDatabaseMissing('rest_days', ['id' => $rest->id]);

        $this->assertSame(1, $this->employee->notifications()->count());
        $this->assertSame('vacation_decision', $this->employee->notifications()->first()->data['kind']);
    }

    public function test_reject_keeps_rest_day(): void
    {
        [$row, $rest] = $this->pendingWithRest();

        $this->actingAs($this->leader)
            ->post(route('team-leader.approvals.vacations.reject', $row))
            ->assertRedirect()
            ->assertSessionHas('success');

        $row->refresh();
        $this->assertSame(Vacation::STATUS_REJECTED, $row->status);
        $this->assertSame($this->leader->id, $row->decided_by);
        $this->assertNull($row->replace_rest_day_id);
        $this->assertDatabaseHas('rest_days', ['id' => $rest->id]);
    }

    public function test_manager_approves_team_leader_self_vacation(): void
    {
        $rest = RestDay::factory()->create([
            'user_id' => $this->leader->id,
            'date' => '2026-08-14',
            'created_by' => $this->manager->id,
        ]);

        $row = Vacation::factory()->create([
            'user_id' => $this->leader->id,
            'team_leader_id' => null,
            'start_date' => '2026-08-12',
            'end_date' => '2026-08-12',
            'days_requested' => 1,
            'replace_rest_date' => '2026-08-14',
            'replace_rest_day_id' => $rest->id,
            'status' => Vacation::STATUS_PENDING,
        ]);

        $this->actingAs($this->manager)
            ->post(route('team-leader.approvals.vacations.approve', $row))
            ->assertRedirect();

        $this->assertSame(Vacation::STATUS_APPROVED, $row->fresh()->status);
        $this->assertSame($this->manager->id, $row->fresh()->decided_by);
        $this->assertDatabaseMissing('rest_days', ['id' => $rest->id]);
    }

    public function test_employee_cannot_approve_vacation(): void
    {
        [$row] = $this->pendingWithRest();

        $this->actingAs($this->employee)
            ->post(route('team-leader.approvals.vacations.approve', $row))
            ->assertForbidden();
    }

    public function test_approve_annual_confirms_balance(): void
    {
        VacationBalance::factory()->create([
            'user_id' => $this->employee->id,
            'year' => 2026,
            'accrued_days' => 10,
            'used_days' => 0,
            'pending_days' => 5,
            'remaining_days' => 5,
        ]);

        $vacation = Vacation::factory()->create([
            'user_id' => $this->employee->id,
            'team_leader_id' => $this->leader->id,
            'type' => Vacation::TYPE_ANNUAL,
            'start_date' => '2026-08-05',
            'end_date' => '2026-08-09',
            'days_requested' => 5,
            'status' => Vacation::STATUS_PENDING,
        ]);

        $this->actingAs($this->leader)
            ->post(route('team-leader.approvals.vacations.approve', $vacation))
            ->assertRedirect();

        $balance = VacationBalance::query()->where('user_id', $this->employee->id)->where('year', 2026)->first();
        $this->assertSame('0.0', $balance->pending_days);
        $this->assertSame('5.0', $balance->used_days);
        $this->assertSame('5.0', $balance->remaining_days);
    }

    public function test_reject_sick_releases_pending_balance(): void
    {
        VacationBalance::factory()->create([
            'user_id' => $this->employee->id,
            'year' => 2026,
            'accrued_days' => 10,
            'used_days' => 0,
            'pending_days' => 3,
            'remaining_days' => 7,
        ]);

        $vacation = Vacation::factory()->create([
            'user_id' => $this->employee->id,
            'team_leader_id' => $this->leader->id,
            'type' => Vacation::TYPE_SICK,
            'start_date' => '2026-08-05',
            'end_date' => '2026-08-07',
            'days_requested' => 3,
            'status' => Vacation::STATUS_PENDING,
        ]);

        $this->actingAs($this->leader)
            ->post(route('team-leader.approvals.vacations.reject', $vacation))
            ->assertRedirect();

        $balance = VacationBalance::query()->where('user_id', $this->employee->id)->where('year', 2026)->first();
        $this->assertSame('0.0', $balance->pending_days);
        $this->assertSame('10.0', $balance->remaining_days);
    }

    public function test_either_team_leader_sees_and_can_decide_the_same_member_vacation(): void
    {
        app()->setLocale('en');

        $leaderA = User::factory()->teamLeader()->create(['name_en' => 'Alpha Vac TL']);
        $leaderB = User::factory()->teamLeader()->create(['name_en' => 'Beta Vac TL']);
        $leaderC = User::factory()->teamLeader()->create(['name_en' => 'Gamma Vac TL']);
        $member = User::factory()->employee()->create([
            'name_en' => 'Dual Vacation Member',
        ]);
        app(TeamMembershipService::class)->syncLeaders($member, [$leaderA->id, $leaderB->id]);

        $rest = RestDay::factory()->create([
            'user_id' => $member->id,
            'date' => '2026-08-21',
            'created_by' => $leaderA->id,
        ]);
        $row = Vacation::factory()->create([
            'user_id' => $member->id,
            'team_leader_id' => $leaderA->id,
            'type' => Vacation::TYPE_EMERGENCY,
            'start_date' => '2026-08-19',
            'end_date' => '2026-08-19',
            'days_requested' => 1,
            'replace_rest_date' => '2026-08-21',
            'replace_rest_day_id' => $rest->id,
            'status' => Vacation::STATUS_PENDING,
        ]);

        $this->actingAs($leaderA)
            ->get(route('team-leader.approvals'))
            ->assertOk()
            ->assertSee('Dual Vacation Member', false)
            ->assertSee(__('vacations.fields.submitted_for_team'), false)
            ->assertSee('Alpha Vac TL', false)
            ->assertSee('Beta Vac TL', false);

        $this->actingAs($leaderC)
            ->post(route('team-leader.approvals.vacations.approve', $row))
            ->assertForbidden();

        $this->actingAs($leaderB)
            ->post(route('team-leader.approvals.vacations.approve', $row))
            ->assertRedirect()
            ->assertSessionHas('success');

        $row->refresh();
        $this->assertSame(Vacation::STATUS_APPROVED, $row->status);
        $this->assertSame($leaderB->id, $row->decided_by);
        $this->assertDatabaseMissing('rest_days', ['id' => $rest->id]);

        $this->actingAs($leaderA)
            ->get(route('team-leader.approvals', ['filter' => 'decided']))
            ->assertOk()
            ->assertSee(__('vacations.fields.decided_by'), false)
            ->assertSee('Beta Vac TL', false);
    }
}
