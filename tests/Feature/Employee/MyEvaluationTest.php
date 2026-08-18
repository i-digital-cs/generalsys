<?php

namespace Tests\Feature\Employee;

use App\Models\Evaluation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Gate 3.6 / T-3.4 — employee views own evaluation; cannot edit scores.
 */
class MyEvaluationTest extends TestCase
{
    use RefreshDatabase;

    private User $employee;

    private User $other;

    private User $leader;

    protected function setUp(): void
    {
        parent::setUp();

        $this->leader = User::factory()->teamLeader()->create();
        $this->employee = User::factory()->employee()->create([
            'team_leader_id' => $this->leader->id,
            'language_preference' => 'en',
        ]);
        $this->other = User::factory()->employee()->create([
            'team_leader_id' => $this->leader->id,
        ]);
    }

    public function test_employee_sees_empty_when_no_saved_evaluation(): void
    {
        app()->setLocale('en');

        $this->actingAs($this->employee)
            ->get(route('employee.evaluation', ['month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertSee(__('evaluation.me.title'), false)
            ->assertSee(__('evaluation.me.empty_title'), false)
            ->assertDontSee('name="evaluation_text"', false)
            ->assertDontSee(__('evaluation.tl.save'), false);
    }

    public function test_employee_sees_own_saved_scores_and_text_read_only(): void
    {
        app()->setLocale('en');

        Evaluation::factory()->create([
            'user_id' => $this->employee->id,
            'team_leader_id' => $this->leader->id,
            'month' => 8,
            'year' => 2026,
            'performance_score' => 90,
            'behavior_score' => 85,
            'productivity_score' => 88,
            'interest_return_score' => 80,
            'attendance_score' => 95,
            'total_score' => 87.6,
            'evaluation_text' => 'Keep up the good work.',
        ]);

        Evaluation::factory()->create([
            'user_id' => $this->other->id,
            'team_leader_id' => $this->leader->id,
            'month' => 8,
            'year' => 2026,
            'evaluation_text' => 'Secret other evaluation',
            'total_score' => 50,
        ]);

        $this->actingAs($this->employee)
            ->get(route('employee.evaluation', ['month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertSee(__('evaluation.fields.performance'), false)
            ->assertSee('87.60', false)
            ->assertSee('Keep up the good work.', false)
            ->assertDontSee('Secret other evaluation', false)
            ->assertDontSee(__('evaluation.tl.save'), false);
    }

    public function test_employee_sees_both_team_leaders_labeled(): void
    {
        app()->setLocale('en');
        $leaderB = User::factory()->teamLeader()->create([
            'name_en' => 'Second Leader',
            'language_preference' => 'en',
        ]);
        $this->leader->update(['name_en' => 'First Leader']);

        Evaluation::factory()->create([
            'user_id' => $this->employee->id,
            'team_leader_id' => $this->leader->id,
            'month' => 8,
            'year' => 2026,
            'total_score' => 90,
            'evaluation_text' => 'Feedback from first',
        ]);
        Evaluation::factory()->create([
            'user_id' => $this->employee->id,
            'team_leader_id' => $leaderB->id,
            'month' => 8,
            'year' => 2026,
            'total_score' => 70,
            'evaluation_text' => 'Feedback from second',
        ]);

        $this->actingAs($this->employee)
            ->get(route('employee.evaluation', ['month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertSee('First Leader', false)
            ->assertSee('Second Leader', false)
            ->assertSee('Feedback from first', false)
            ->assertSee('Feedback from second', false)
            ->assertSee('90.00', false)
            ->assertSee('70.00', false);
    }

    public function test_employee_history_lists_own_months_only(): void
    {
        Evaluation::factory()->create([
            'user_id' => $this->employee->id,
            'team_leader_id' => $this->leader->id,
            'month' => 7,
            'year' => 2026,
            'total_score' => 91,
        ]);

        $this->actingAs($this->employee)
            ->get(route('employee.evaluation', ['month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertSee(__('evaluation.me.history'), false)
            ->assertSee('91.00', false)
            ->assertSee('evaluation?month=7&amp;year=2026', false);
    }

    public function test_guest_is_redirected(): void
    {
        $this->get(route('employee.evaluation'))->assertRedirect(route('login'));
    }
}
