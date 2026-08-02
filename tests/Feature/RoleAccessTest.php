<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect('/login');
        $this->get(route('admin.employees.index'))->assertRedirect('/login');
        $this->get(route('dashboard'))->assertRedirect('/login');
        $this->get(route('employee.profile'))->assertRedirect('/login');
    }

    public function test_employee_gets_403_on_all_admin_routes(): void
    {
        $employee = User::factory()->employee()->create();
        $other = User::factory()->employee()->create();

        $this->actingAs($employee);

        $this->get(route('admin.dashboard'))->assertForbidden();
        $this->get(route('admin.employees.index'))->assertForbidden();
        $this->get(route('admin.employees.create'))->assertForbidden();
        $this->get(route('admin.employees.edit', $other))->assertForbidden();
        $this->get(route('admin.settings'))->assertForbidden();
    }

    public function test_team_leader_gets_403_on_all_admin_routes(): void
    {
        $leader = User::factory()->teamLeader()->create();
        $member = User::factory()->employee()->create(['team_leader_id' => $leader->id]);

        $this->actingAs($leader);

        $this->get(route('admin.dashboard'))->assertForbidden();
        $this->get(route('admin.employees.index'))->assertForbidden();
        $this->get(route('admin.employees.create'))->assertForbidden();
        $this->get(route('admin.employees.edit', $member))->assertForbidden();
        $this->get(route('admin.settings'))->assertForbidden();
    }

    public function test_manager_can_open_admin_routes(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager);

        $this->get(route('admin.dashboard'))->assertOk();
        $this->get(route('admin.employees.index'))->assertOk();
        $this->get(route('admin.employees.create'))->assertOk();
        $this->get(route('admin.settings'))->assertOk();
    }

    public function test_team_leader_can_open_generic_dashboard(): void
    {
        $leader = User::factory()->teamLeader()->create();

        $this->actingAs($leader)
            ->get(route('dashboard'))
            ->assertOk();
    }
}
