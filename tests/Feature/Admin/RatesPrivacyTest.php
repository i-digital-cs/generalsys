<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Gate 1.5 — rates are Manager + Assistant; TEAM_LEADER never sees money.
 */
class RatesPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_sees_rates_on_employee_edit(): void
    {
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee()->create(['base_salary' => 5000]);
        $employee->profile()->create(['employee_code' => 'EMP-R1']);

        $this->actingAs($manager)
            ->get(route('admin.employees.edit', $employee))
            ->assertOk()
            ->assertSee('base_salary')
            ->assertSee(__('employees.rates_formula_hint'), false);
    }

    public function test_team_leader_gets_403_on_rates_screen(): void
    {
        $leader = User::factory()->teamLeader()->create();
        $member = User::factory()->employee()->create(['team_leader_id' => $leader->id]);

        $this->actingAs($leader)
            ->get(route('admin.employees.edit', $member))
            ->assertForbidden();

        $this->actingAs($leader)
            ->put(route('admin.employees.update', $member), ['base_salary' => 1])
            ->assertForbidden();
    }

    public function test_employee_gets_403_on_rates_screen(): void
    {
        $employee = User::factory()->employee()->create();

        $this->actingAs($employee)
            ->get(route('admin.employees.edit', $employee))
            ->assertForbidden();
    }

    public function test_employee_self_profile_renders_no_money_fields(): void
    {
        $employee = User::factory()->employee()->create([
            'base_salary' => 9876,
        ]);
        $employee->profile()->create(['employee_code' => 'EMP-P1']);

        $this->actingAs($employee)
            ->get(route('employee.profile'))
            ->assertOk()
            ->assertDontSee('base_salary')
            ->assertDontSee(__('employees.sections.rates'));
    }

    public function test_assistant_sees_rates_on_employee_edit(): void
    {
        $assistant = User::factory()->assistant()->create();
        $employee = User::factory()->employee()->create(['base_salary' => 5000]);
        $employee->profile()->create(['employee_code' => 'EMP-R2']);

        $this->actingAs($assistant)
            ->get(route('admin.employees.edit', $employee))
            ->assertOk()
            ->assertSee('base_salary')
            ->assertSee(__('employees.rates_formula_hint'), false);
    }

    public function test_policy_denies_rates_update_to_team_leader_and_employee(): void
    {
        $manager = User::factory()->manager()->create();
        $assistant = User::factory()->assistant()->create();
        $leader = User::factory()->teamLeader()->create();
        $employee = User::factory()->employee()->create();

        $this->assertTrue($manager->can('updateRates', $employee));
        $this->assertTrue($assistant->can('updateRates', $employee));
        $this->assertFalse($leader->can('updateRates', $employee));
        $this->assertFalse($employee->can('updateRates', $employee));
        $this->assertFalse($leader->can('view', $employee));
        $this->assertTrue($employee->can('view', $employee));
        $this->assertTrue($assistant->can('view', $employee));
        $this->assertTrue($assistant->can('update', $employee));
    }
}
