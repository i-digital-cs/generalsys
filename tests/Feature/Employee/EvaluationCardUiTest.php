<?php

namespace Tests\Feature\Employee;

use App\Models\Evaluation;
use App\Models\User;
use App\Support\Ui\ListViewMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EvaluationCardUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_evaluation_uses_card_shell_not_history_table(): void
    {
        $leader = User::factory()->teamLeader()->create();
        $employee = User::factory()->employee()->create([
            'team_leader_id' => $leader->id,
            'language_preference' => 'en',
        ]);
        ListViewMode::put($employee, ListViewMode::CARDS);

        Evaluation::factory()->create([
            'user_id' => $employee->id,
            'team_leader_id' => $leader->id,
            'month' => 8,
            'year' => 2026,
            'total_score' => 88.5,
        ]);

        $this->actingAs($employee)
            ->get(route('employee.evaluation', ['month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertSee('data-eval-card-ui="employee"', false)
            ->assertSee('gh-record-card', false)
            ->assertSee('88.50', false)
            ->assertDontSee('table-hr', false)
            ->assertDontSee('data-table-filterable', false);
    }

    public function test_tl_evaluations_index_uses_card_grid(): void
    {
        $leader = User::factory()->teamLeader()->create(['language_preference' => 'en']);
        ListViewMode::put($leader, ListViewMode::CARDS);
        $employee = User::factory()->employee()->create([
            'team_leader_id' => $leader->id,
            'name_en' => 'Card Eval Member',
            'language_preference' => 'en',
        ]);

        $this->actingAs($leader)
            ->get(route('team-leader.evaluations', ['month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertSee('data-eval-card-ui="tl-index"', false)
            ->assertSee('eval-card-ui__grid', false)
            ->assertSee('Card Eval Member', false)
            ->assertSee('data-filterable-item', false)
            ->assertDontSee('table-hr', false);
    }
}
