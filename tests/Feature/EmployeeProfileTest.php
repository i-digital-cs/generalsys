<?php

namespace Tests\Feature;

use App\Models\RestDay;
use App\Models\User;
use App\Services\Employees\TeamMembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_sees_own_profile(): void
    {
        $employee = User::factory()->employee()->create([
            'name_en' => 'Radwa Adel',
            'job_title_en' => 'Customer Service Agent',
            'job_title_ar' => 'أخصائي خدمة عملاء',
        ]);
        $employee->profile()->create(['employee_code' => 'EMP-P1', 'national_id' => '12345678901234']);
        $employee->contracts()->create([
            'hire_date' => '2024-01-01', 'contract_start' => '2024-01-01',
            'contract_end' => '2026-01-01', 'contract_type' => 'سنة', 'is_active' => true,
        ]);
        $employee->qualifications()->create(['degree' => 'BSc', 'specialization' => 'CS']);
        $employee->emergencyContacts()->create(['name' => 'Mother', 'phone' => '010']);

        $this->withSession(['locale' => 'en'])->actingAs($employee)
            ->get(route('employee.profile'))
            ->assertOk()
            ->assertSee('Radwa Adel')
            ->assertSee('Customer Service Agent')
            ->assertSee('EMP-P1')
            ->assertSee('BSc')
            ->assertSee('Mother');
    }

    public function test_employee_sees_work_schedule_and_rest_days_read_only(): void
    {
        $employee = User::factory()->employee()->create([
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
        ]);
        $employee->profile()->create(['employee_code' => 'EMP-WS1']);
        RestDay::factory()->create([
            'user_id' => $employee->id,
            'date' => '2026-08-07',
        ]);
        RestDay::factory()->create([
            'user_id' => $employee->id,
            'date' => '2026-08-08',
        ]);

        $this->actingAs($employee)
            ->get(route('employee.profile'))
            ->assertOk()
            ->assertSee('10:00')
            ->assertSee('18:00')
            ->assertSee(__('employees.fields.job_title'))
            ->assertSee('2026-08-07', false)
            ->assertSee('2026-08-08', false);
    }

    public function test_profile_renders_gender_and_marital_status_values(): void
    {
        $employee = User::factory()->employee()->create();
        $employee->profile()->create([
            'employee_code' => 'EMP-G1',
            'gender' => 'FEMALE',
            'marital_status' => 'SINGLE',
        ]);

        // Regression: nested quotes in component attributes once leaked raw Blade.
        $this->actingAs($employee)
            ->get(route('employee.profile'))
            ->assertOk()
            ->assertSee(__('employees.genders.FEMALE'))
            ->assertSee(__('employees.marital_statuses.SINGLE'))
            ->assertDontSee('genders.')
            ->assertDontSee(': null', false);
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

    public function test_profile_shows_both_team_leaders(): void
    {
        $a = User::factory()->teamLeader()->create([
            'name_en' => 'Profile Lead A',
            'email' => 'lead-a@hrpro.test',
        ]);
        $b = User::factory()->teamLeader()->create([
            'name_en' => 'Profile Lead B',
            'email' => 'lead-b@hrpro.test',
        ]);
        $a->profile()->create(['employee_code' => 'TL-A1', 'phone' => '01000000001']);
        $employee = User::factory()->employee()->create();
        $employee->profile()->create(['employee_code' => 'EMP-TL2']);
        app(TeamMembershipService::class)->syncLeaders($employee, [$a->id, $b->id]);

        $this->withSession(['locale' => 'en'])->actingAs($employee)
            ->get(route('employee.profile'))
            ->assertOk()
            ->assertSee(__('profile.my_team_leaders'))
            ->assertSee('Profile Lead A')
            ->assertSee('Profile Lead B')
            ->assertSee('lead-a@hrpro.test')
            ->assertSee('01000000001');
    }

    public function test_guest_is_redirected(): void
    {
        $this->get(route('employee.profile'))->assertRedirect('/login');
    }
}
