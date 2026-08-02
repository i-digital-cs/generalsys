<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_sees_own_profile(): void
    {
        $employee = User::factory()->employee()->create(['name_en' => 'Radwa Adel']);
        $employee->profile()->create(['employee_code' => 'EMP-P1', 'national_id' => '12345678901234']);
        $employee->contracts()->create([
            'hire_date' => '2024-01-01', 'contract_start' => '2024-01-01',
            'contract_end' => '2026-01-01', 'contract_type' => 'سنة', 'is_active' => true,
        ]);
        $employee->qualifications()->create(['degree' => 'BSc', 'specialization' => 'CS']);
        $employee->emergencyContacts()->create(['name' => 'Mother', 'phone' => '010']);

        $this->actingAs($employee)
            ->get(route('employee.profile'))
            ->assertOk()
            ->assertSee('Radwa Adel')
            ->assertSee('EMP-P1')
            ->assertSee('BSc')
            ->assertSee('Mother');
    }

    public function test_employee_sees_work_schedule_and_rest_days_read_only(): void
    {
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee()->create([
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
        ]);
        $employee->profile()->create(['employee_code' => 'EMP-WS1']);
        $employee->restDays()->create(['date' => '2026-08-14', 'created_by' => $manager->id]);

        $this->actingAs($employee)
            ->get(route('employee.profile'))
            ->assertOk()
            ->assertSee('10:00')
            ->assertSee('18:00')
            ->assertSee('2026-08-14')
            // No delete buttons on the read-only view.
            ->assertDontSee('rest-days');
    }

    public function test_employee_cannot_view_another_employees_profile(): void
    {
        $employeeA = User::factory()->employee()->create();
        $employeeB = User::factory()->employee()->create();

        $this->actingAs($employeeA)
            ->get(route('employee.profile.show', $employeeB))
            ->assertForbidden();
    }

    public function test_team_leader_cannot_open_employee_profiles(): void
    {
        $leader = User::factory()->teamLeader()->create();
        $member = User::factory()->employee()->create(['team_leader_id' => $leader->id]);

        $this->actingAs($leader)
            ->get(route('employee.profile.show', $member))
            ->assertForbidden();
    }

    public function test_guest_is_redirected(): void
    {
        $this->get(route('employee.profile'))->assertRedirect('/login');
    }
}
