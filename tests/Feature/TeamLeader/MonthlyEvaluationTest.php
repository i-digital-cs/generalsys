<?php

namespace Tests\Feature\TeamLeader;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Gate 3.5 / T-3.3 — TL monthly evaluation screen (5 bars + text).
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

        Attendance::factory()->create([
            'user_id' => $this->employee->id,
            'date' => '2026-08-03',
            'shift_type' => Attendance::SHIFT_FULL_TIME,
            'performance_score' => 90,
            'behavior_score' => 80,
            'productivity_score' => 85,
            'interest_return_score' => 75,
            'attendance_score' => 100,
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
            'month' => 8,
            'year' => 2026,
            'evaluation_text' => 'Solid contribution this month.',
            'total_score' => 86.00,
        ]);
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
