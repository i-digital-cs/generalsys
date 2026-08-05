<?php

namespace Tests\Feature;

use App\Models\Advance;
use App\Models\PayrollRecord;
use App\Models\PenaltyRule;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** T-6.5 — list screens expose shared table-hr + filter toolbar. */
class TableFilterUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_list_screens_use_table_toolbar(): void
    {
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee()->create();

        PayrollRecord::factory()->create([
            'user_id' => $employee->id,
            'created_by' => $manager->id,
            'month' => (int) now()->month,
            'year' => (int) now()->year,
            'status' => PayrollRecord::STATUS_DRAFT,
        ]);

        Advance::factory()->create([
            'user_id' => $employee->id,
        ]);

        PenaltyRule::factory()->create();

        foreach ([
            route('admin.employees.index'),
            route('admin.payroll.index', ['month' => now()->month, 'year' => now()->year]),
            route('admin.advances.index'),
            route('admin.config.penalties'),
        ] as $url) {
            $this->actingAs($manager)
                ->get($url)
                ->assertOk()
                ->assertSee('table-hr', false)
                ->assertSee('data-table-filter-root', false);
        }
    }

    public function test_team_leader_list_screens_use_table_toolbar(): void
    {
        $leader = User::factory()->teamLeader()->create();
        $employee = User::factory()->employee()->create(['team_leader_id' => $leader->id]);

        Permission::factory()->create([
            'user_id' => $employee->id,
            'team_leader_id' => $leader->id,
            'status' => Permission::STATUS_PENDING,
        ]);

        foreach ([
            route('team-leader.approvals'),
            route('team-leader.evaluations'),
            route('team-leader.shifts'),
            route('team-leader.rest-days'),
        ] as $url) {
            $this->actingAs($leader)
                ->get($url)
                ->assertOk()
                ->assertSee('data-table-filter-root', false);
        }
    }

    public function test_employee_salary_list_uses_table_toolbar(): void
    {
        $employee = User::factory()->employee()->create(['language_preference' => 'en']);

        PayrollRecord::factory()->create([
            'user_id' => $employee->id,
            'month' => (int) now()->month,
            'year' => (int) now()->year,
            'status' => PayrollRecord::STATUS_APPROVED,
        ]);

        $this->actingAs($employee)
            ->get(route('employee.salary'))
            ->assertOk()
            ->assertSee('table-hr', false)
            ->assertSee('data-table-search', false);
    }
}
