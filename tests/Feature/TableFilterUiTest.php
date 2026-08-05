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
            route('admin.payroll.index', ['month' => now()->month, 'year' => now()->year]),
            route('admin.config.penalties'),
        ] as $url) {
            $this->actingAs($manager)
                ->get($url)
                ->assertOk()
                ->assertSee('table-hr', false)
                ->assertSee('data-table-filter-root', false);
        }
    }

    public function test_paginated_admin_lists_filter_server_side_only(): void
    {
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee()->create();

        Advance::factory()->create(['user_id' => $employee->id]);

        // Paginated lists: a JS toolbar would hide matches on other pages, so the
        // server-side GET form is the single filter — no client-side toolbar.
        $this->actingAs($manager)
            ->get(route('admin.employees.index'))
            ->assertOk()
            ->assertSee('table-hr', false)
            ->assertSee('name="q"', false)
            ->assertDontSee('data-table-filter-root', false);

        $this->actingAs($manager)
            ->get(route('admin.advances.index'))
            ->assertOk()
            ->assertSee('table-hr', false)
            ->assertSee('name="user_id"', false)
            ->assertDontSee('data-table-filter-root', false);
    }

    public function test_paginated_team_leader_lists_filter_server_side_only(): void
    {
        $leader = User::factory()->teamLeader()->create();
        $employee = User::factory()->employee()->create(['team_leader_id' => $leader->id]);

        Permission::factory()->create([
            'user_id' => $employee->id,
            'team_leader_id' => $leader->id,
            'status' => Permission::STATUS_PENDING,
        ]);

        // Reports: GET form (employee + status) is the single filter.
        $this->actingAs($leader)
            ->get(route('team-leader.reports'))
            ->assertOk()
            ->assertSee('name="employee_id"', false)
            ->assertDontSee('data-table-filter-root', false);

        // Approvals: status pills (?filter=…) are the single filter.
        $this->actingAs($leader)
            ->get(route('team-leader.approvals'))
            ->assertOk()
            ->assertSee('table-hr', false)
            ->assertSee(route('team-leader.approvals', ['filter' => 'pending']), false)
            ->assertDontSee('data-table-filter-root', false);
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
