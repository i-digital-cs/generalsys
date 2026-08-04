<?php

namespace Tests\Feature\Employee;

use App\Models\RestDay;
use App\Models\User;
use App\Models\Vacation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Vacation Force (product A): REJECTED → FORCED; rest day kept.
 */
class VacationForceTest extends TestCase
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
            'language_preference' => 'en',
        ]);
        $this->manager = User::factory()->manager()->create([
            'language_preference' => 'en',
        ]);
    }

    private function rejectedWithRest(string $off = '2026-08-05', string $restDate = '2026-08-07'): array
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
            'replace_rest_day_id' => null,
            'status' => Vacation::STATUS_REJECTED,
        ]);

        return [$vacation, $rest];
    }

    public function test_employee_sees_force_on_rejected_vacation(): void
    {
        app()->setLocale('en');
        $this->rejectedWithRest();

        $this->actingAs($this->employee)
            ->get(route('employee.requests', ['tab' => 'vacations']))
            ->assertOk()
            ->assertSee(__('vacations.force'), false)
            ->assertSee(__('vacations.statuses.REJECTED'), false);
    }

    public function test_team_leader_sees_force_on_approvals(): void
    {
        app()->setLocale('en');
        $this->rejectedWithRest();

        $this->actingAs($this->leader)
            ->get(route('team-leader.approvals', ['filter' => 'decided']))
            ->assertOk()
            ->assertSee(__('vacations.force'), false);
    }

    public function test_employee_can_force_own_rejected_vacation_and_keeps_rest_day(): void
    {
        [$row, $rest] = $this->rejectedWithRest();

        $this->actingAs($this->employee)
            ->post(route('employee.requests.vacations.force', $row))
            ->assertRedirect(route('employee.requests', ['tab' => 'vacations']))
            ->assertSessionHas('success');

        $row->refresh();
        $this->assertSame(Vacation::STATUS_FORCED, $row->status);
        $this->assertNull($row->replace_rest_day_id);
        $this->assertDatabaseHas('rest_days', ['id' => $rest->id]);

        $this->assertSame('vacation_forced', $this->leader->notifications()->first()->data['kind']);
    }

    public function test_team_leader_can_force_team_rejected_vacation(): void
    {
        [$row, $rest] = $this->rejectedWithRest();

        $this->actingAs($this->leader)
            ->post(route('team-leader.approvals.vacations.force', $row))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(Vacation::STATUS_FORCED, $row->fresh()->status);
        $this->assertDatabaseHas('rest_days', ['id' => $rest->id]);
        $this->assertSame(1, $this->employee->notifications()->count());
        $this->assertSame('vacation_forced', $this->employee->notifications()->first()->data['kind']);
    }

    public function test_manager_can_force_rejected_vacation(): void
    {
        [$row] = $this->rejectedWithRest();

        $this->actingAs($this->manager)
            ->post(route('team-leader.approvals.vacations.force', $row))
            ->assertRedirect();

        $this->assertSame(Vacation::STATUS_FORCED, $row->fresh()->status);
    }

    public function test_cannot_force_pending_vacation(): void
    {
        $rest = RestDay::factory()->create([
            'user_id' => $this->employee->id,
            'date' => '2026-08-07',
            'created_by' => $this->leader->id,
        ]);

        $row = Vacation::factory()->create([
            'user_id' => $this->employee->id,
            'team_leader_id' => $this->leader->id,
            'start_date' => '2026-08-05',
            'end_date' => '2026-08-05',
            'days_requested' => 1,
            'replace_rest_date' => '2026-08-07',
            'replace_rest_day_id' => $rest->id,
            'status' => Vacation::STATUS_PENDING,
        ]);

        $this->actingAs($this->employee)
            ->post(route('employee.requests.vacations.force', $row))
            ->assertSessionHasErrors('vacation');

        $this->assertSame(Vacation::STATUS_PENDING, $row->fresh()->status);
    }
}
