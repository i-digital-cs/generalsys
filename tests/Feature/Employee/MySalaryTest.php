<?php

namespace Tests\Feature\Employee;

use App\Models\PayrollRecord;
use App\Models\User;
use App\Services\Employees\TeamMembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Gate 4.8 — employee sees own APPROVED/PAID only; DRAFT hidden.
 */
class MySalaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_sees_approved_and_paid_not_draft(): void
    {
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee()->create();

        PayrollRecord::factory()->create([
            'user_id' => $employee->id,
            'month' => 5,
            'year' => 2026,
            'status' => PayrollRecord::STATUS_DRAFT,
            'net_pay' => 1111,
            'created_by' => $manager->id,
        ]);
        $approved = PayrollRecord::factory()->create([
            'user_id' => $employee->id,
            'month' => 6,
            'year' => 2026,
            'status' => PayrollRecord::STATUS_APPROVED,
            'net_pay' => 2808,
            'currency' => 'EGP',
            'created_by' => $manager->id,
        ]);
        PayrollRecord::factory()->create([
            'user_id' => $employee->id,
            'month' => 4,
            'year' => 2026,
            'status' => PayrollRecord::STATUS_PAID,
            'net_pay' => 2500,
            'created_by' => $manager->id,
        ]);

        $this->actingAs($employee)
            ->get(route('employee.salary'))
            ->assertOk()
            ->assertSee(__('salary.title'), false)
            ->assertSee('2,808', false)
            ->assertSee('2,500', false)
            ->assertDontSee('1,111', false);

        $this->actingAs($employee)
            ->get(route('employee.salary.show', $approved))
            ->assertOk()
            ->assertSee(__('salary.read_only_hint'), false)
            ->assertSee(__('payroll.earnings'), false);
    }

    public function test_payslip_shows_both_team_leaders(): void
    {
        $manager = User::factory()->manager()->create();
        $a = User::factory()->teamLeader()->create(['name_en' => 'Payslip Lead A']);
        $b = User::factory()->teamLeader()->create(['name_en' => 'Payslip Lead B']);
        $employee = User::factory()->employee()->create();
        app(TeamMembershipService::class)->syncLeaders($employee, [$a->id, $b->id]);

        $approved = PayrollRecord::factory()->create([
            'user_id' => $employee->id,
            'month' => 8,
            'year' => 2026,
            'status' => PayrollRecord::STATUS_APPROVED,
            'net_pay' => 2808,
            'created_by' => $manager->id,
        ]);

        $this->withSession(['locale' => 'en'])->actingAs($employee)
            ->get(route('employee.salary.show', $approved))
            ->assertOk()
            ->assertSee('Payslip Lead A')
            ->assertSee('Payslip Lead B');
    }

    public function test_employee_cannot_open_own_draft(): void
    {
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee()->create();
        $draft = PayrollRecord::factory()->create([
            'user_id' => $employee->id,
            'status' => PayrollRecord::STATUS_DRAFT,
            'created_by' => $manager->id,
        ]);

        $this->actingAs($employee)
            ->get(route('employee.salary.show', $draft))
            ->assertForbidden();
    }

    public function test_employee_cannot_open_another_employees_payslip(): void
    {
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee()->create();
        $other = User::factory()->employee()->create();
        $payslip = PayrollRecord::factory()->create([
            'user_id' => $other->id,
            'status' => PayrollRecord::STATUS_APPROVED,
            'created_by' => $manager->id,
        ]);

        $this->actingAs($employee)
            ->get(route('employee.salary.show', $payslip))
            ->assertForbidden();
    }

    public function test_team_leader_cannot_open_my_salary(): void
    {
        $leader = User::factory()->teamLeader()->create();

        $this->actingAs($leader)
            ->get(route('employee.salary'))
            ->assertForbidden();
    }
}
