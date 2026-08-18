<?php

namespace Tests\Feature\TeamLeader;

use App\Models\Attendance;
use App\Models\AttendanceDailyScore;
use App\Models\User;
use App\Services\Employees\TeamMembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Gate 3.5 / T-3.3 / T-8.5 — TL monthly evaluation screen (5 bars + text); dual TL no overwrite.
 */
class MonthlyEvaluationTest extends TestCase
{
    use RefreshDatabase;

    private User $leader;

    private User $employee;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->leader = User::factory()->teamLeader()->create([
            'name_en' => 'Eval Leader A',
            'language_preference' => 'en',
        ]);
        $this->employee = User::factory()->employee()->create([
            'team_leader_id' => $this->leader->id,
            'name_en' => 'Eval Teammate',
            'language_preference' => 'en',
        ]);
        $this->manager = User::factory()->manager()->create([
            'language_preference' => 'en',
        ]);

        $day = Attendance::factory()->create([
            'user_id' => $this->employee->id,
            'date' => '2026-08-03',
            'shift_type' => Attendance::SHIFT_FULL_TIME,
            'attendance_score' => 100,
        ]);

        AttendanceDailyScore::factory()->create([
            'attendance_id' => $day->id,
            'team_leader_id' => $this->leader->id,
            'scored_by' => $this->leader->id,
            'performance_score' => 90,
            'behavior_score' => 80,
            'productivity_score' => 85,
            'interest_return_score' => 75,
        ]);
    }

    public function test_team_leader_sees_team_list_with_total(): void
    {
        app()->setLocale('en');

        $this->actingAs($this->leader)
            ->get(route('team-leader.evaluations', ['month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertSee(__('evaluation.tl.title'), false)
            ->assertSee('Eval Teammate', false)
            ->assertSee('86.00', false);
    }

    public function test_team_leader_can_open_and_save_monthly_evaluation(): void
    {
        app()->setLocale('en');

        $this->actingAs($this->leader)
            ->get(route('team-leader.evaluations.show', [
                'employee' => $this->employee,
                'month' => 8,
                'year' => 2026,
            ]))
            ->assertOk()
            ->assertSee(__('evaluation.fields.performance'), false)
            ->assertSee(__('evaluation.fields.total'), false)
            ->assertSee(__('evaluation.tl.save'), false);

        $this->actingAs($this->leader)
            ->post(route('team-leader.evaluations.store', $this->employee), [
                'month' => 8,
                'year' => 2026,
                'evaluation_text' => 'Solid contribution this month.',
            ])
            ->assertRedirect(route('team-leader.evaluations.show', [
                'employee' => $this->employee,
                'month' => 8,
                'year' => 2026,
            ]))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('evaluations', [
            'user_id' => $this->employee->id,
            'team_leader_id' => $this->leader->id,
            'month' => 8,
            'year' => 2026,
            'evaluation_text' => 'Solid contribution this month.',
            'total_score' => 86.00,
        ]);
    }

    public function test_two_team_leaders_save_separate_monthly_rows(): void
    {
        app()->setLocale('en');
        $leaderB = User::factory()->teamLeader()->create([
            'name_en' => 'Eval Leader B',
            'language_preference' => 'en',
        ]);
        app(TeamMembershipService::class)->syncLeaders($this->employee, [
            $this->leader->id,
            $leaderB->id,
        ]);

        $day = Attendance::query()->where('user_id', $this->employee->id)->first();
        AttendanceDailyScore::factory()->create([
            'attendance_id' => $day->id,
            'team_leader_id' => $leaderB->id,
            'scored_by' => $leaderB->id,
            'performance_score' => 70,
            'behavior_score' => 70,
            'productivity_score' => 70,
            'interest_return_score' => 70,
        ]);

        $this->actingAs($this->leader)
            ->post(route('team-leader.evaluations.store', $this->employee), [
                'month' => 8,
                'year' => 2026,
                'evaluation_text' => 'Note from A',
            ])
            ->assertRedirect();

        $this->actingAs($leaderB)
            ->post(route('team-leader.evaluations.store', $this->employee), [
                'month' => 8,
                'year' => 2026,
                'evaluation_text' => 'Note from B',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('evaluations', [
            'user_id' => $this->employee->id,
            'team_leader_id' => $this->leader->id,
            'evaluation_text' => 'Note from A',
            'total_score' => 86.00,
        ]);
        $this->assertDatabaseHas('evaluations', [
            'user_id' => $this->employee->id,
            'team_leader_id' => $leaderB->id,
            'evaluation_text' => 'Note from B',
            'total_score' => 76.00, // (70*4 + 100)/5
        ]);

        $this->actingAs($this->leader)
            ->get(route('team-leader.evaluations.show', [
                'employee' => $this->employee,
                'month' => 8,
                'year' => 2026,
            ]))
            ->assertOk()
            ->assertSee('Note from A', false)
            ->assertSee('Eval Leader B', false)
            ->assertSee('Note from B', false);

        $outsider = User::factory()->teamLeader()->create();
        $this->actingAs($outsider)
            ->post(route('team-leader.evaluations.store', $this->employee), [
                'month' => 8,
                'year' => 2026,
                'evaluation_text' => 'Should fail',
            ])
            ->assertForbidden();
    }

    public function test_team_leader_cannot_evaluate_other_team(): void
    {
        $otherLeader = User::factory()->teamLeader()->create();
        $other = User::factory()->employee()->create([
            'team_leader_id' => $otherLeader->id,
        ]);

        $this->actingAs($this->leader)
            ->get(route('team-leader.evaluations.show', $other))
            ->assertForbidden();
    }

    public function test_manager_can_save_for_any_employee(): void
    {
        $this->actingAs($this->manager)
            ->post(route('team-leader.evaluations.store', $this->employee), [
                'month' => 8,
                'year' => 2026,
                'evaluation_text' => 'Manager note.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('evaluations', [
            'user_id' => $this->employee->id,
            'evaluation_text' => 'Manager note.',
            'team_leader_id' => $this->manager->id,
        ]);
    }

    public function test_employee_cannot_open_tl_evaluations(): void
    {
        $this->actingAs($this->employee)
            ->get(route('team-leader.evaluations'))
            ->assertForbidden();
    }
}
