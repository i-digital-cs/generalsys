<?php

namespace Tests\Feature\Admin;

use App\Models\Contract;
use App\Models\Evaluation;
use App\Models\PayrollRecord;
use App\Models\User;
use App\Models\VacationBalance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T-5.1 / Gate 5.1 — Admin annual report year grid.
 */
class AnnualReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_sees_year_grid_and_evaluation_average(): void
    {
        $manager = User::factory()->manager()->create();
        $leader = User::factory()->teamLeader()->create();
        $employee = User::factory()->employee()->create(['currency' => 'EGP']);

        Contract::factory()->create([
            'user_id' => $employee->id,
            'contract_type' => 'سنة',
            'is_active' => true,
        ]);
        VacationBalance::factory()->create([
            'user_id' => $employee->id,
            'year' => 2026,
            'remaining_days' => 12.5,
        ]);

        Evaluation::factory()->create([
            'user_id' => $employee->id,
            'team_leader_id' => $leader->id,
            'month' => 6,
            'year' => 2026,
            'total_score' => 97.40,
        ]);
        Evaluation::factory()->create([
            'user_id' => $employee->id,
            'team_leader_id' => $leader->id,
            'month' => 7,
            'year' => 2026,
            'total_score' => 97.40,
        ]);

        PayrollRecord::factory()->create([
            'user_id' => $employee->id,
            'month' => 6,
            'year' => 2026,
            'status' => PayrollRecord::STATUS_APPROVED,
            'net_pay' => 2808,
            'total_deductions' => 1192,
            'late_penalty' => 54,
            'created_by' => $manager->id,
        ]);

        $this->actingAs($manager)
            ->get(route('admin.reports.annual', [
                'user_id' => $employee->id,
                'year' => 2026,
            ]))
            ->assertOk()
            ->assertSee(__('reports.title'), false)
            ->assertSee(__('reports.year_average'), false)
            ->assertSee('97.40%', false)
            ->assertSee('2,808.00', false)
            ->assertSee(__('reports.grid'), false);
    }

    public function test_team_leader_gets_403(): void
    {
        $leader = User::factory()->teamLeader()->create();

        $this->actingAs($leader)
            ->get(route('admin.reports.annual'))
            ->assertForbidden();
    }

    public function test_index_without_employee_shows_empty_prompt(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)
            ->get(route('admin.reports.annual', ['year' => 2026]))
            ->assertOk()
            ->assertSee(__('reports.select_employee'), false);
    }
}
