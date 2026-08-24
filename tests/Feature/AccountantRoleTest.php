<?php

namespace Tests\Feature;

use App\Models\Advance;
use App\Models\PayrollAdjustment;
use App\Models\PayrollRecord;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T-7.1 — ACCOUNTANT: self-service + payroll (no approve) + advances CRUD;
 * reports to Manager (no team_leader_id).
 */
class AccountantRoleTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $accountant;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->manager()->create();
        $this->accountant = User::factory()->accountant()->create([
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
            'base_salary' => 7000,
        ]);
        $leader = User::factory()->teamLeader()->create();
        $this->employee = User::factory()->employee()->create([
            'team_leader_id' => $leader->id,
            'base_salary' => 6000,
        ]);
    }

    public function test_accountant_has_no_team_leader_and_lands_on_employee_dashboard(): void
    {
        $this->assertNull($this->accountant->team_leader_id);
        $this->assertTrue($this->accountant->isAccountant());

        $this->actingAs($this->accountant)
            ->get(route('employee.dashboard'))
            ->assertOk();
    }

    public function test_accountant_menu_has_payroll_and_advances_not_employees(): void
    {
        $routes = array_column(config('menu.ACCOUNTANT'), 'route');
        $this->assertContains('admin.payroll.index', $routes);
        $this->assertContains('admin.advances.index', $routes);
        $this->assertContains('employee.clock', $routes);
        $this->assertNotContains('admin.employees.index', $routes);
        $this->assertNotContains('admin.settings', $routes);
        $this->assertNotContains('team-leader.approvals', $routes);
    }

    public function test_accountant_can_view_and_generate_payroll_but_not_approve(): void
    {
        $payroll = PayrollRecord::factory()->create([
            'user_id' => $this->employee->id,
            'created_by' => $this->manager->id,
            'status' => PayrollRecord::STATUS_DRAFT,
            'month' => 8,
            'year' => 2026,
        ]);

        $this->actingAs($this->accountant)
            ->get(route('admin.payroll.index', ['month' => 8, 'year' => 2026]))
            ->assertOk();

        $this->actingAs($this->accountant)
            ->get(route('admin.payroll.show', $payroll))
            ->assertOk()
            ->assertDontSee(__('payroll.approve'), false);

        $this->actingAs($this->accountant)
            ->post(route('admin.payroll.generate'), [
                'month' => 8,
                'year' => 2026,
            ])
            ->assertRedirect();

        $this->actingAs($this->accountant)
            ->put(route('admin.payroll.approve', $payroll))
            ->assertForbidden();

        $this->assertSame(PayrollRecord::STATUS_DRAFT, $payroll->fresh()->status);
    }

    public function test_accountant_can_edit_draft_and_add_adjustment(): void
    {
        $payroll = PayrollRecord::factory()->create([
            'user_id' => $this->employee->id,
            'created_by' => $this->manager->id,
            'status' => PayrollRecord::STATUS_DRAFT,
            'full_time_days' => 10,
        ]);

        $this->actingAs($this->accountant)
            ->put(route('admin.payroll.update', $payroll), [
                'full_time_days' => 12,
                'online_half_days' => 0,
                'online_full_days' => 0,
                'rest_days' => 0,
                'overtime_hours' => 0,
                'overtime_multiplier' => 1.5,
                'friday_work_days' => 0,
                'day_off_work_days' => 0,
                'official_holiday_work_days' => 0,
                'hours_deduction' => 0,
                'late_minutes_total' => 0,
                'excused_absence_days' => 0,
                'unexcused_absence_days' => 0,
                'extra_days' => 0,
                'extra_pay' => 0,
            ])
            ->assertRedirect(route('admin.payroll.show', $payroll));

        $this->assertSame(12, (int) $payroll->fresh()->full_time_days);

        $this->actingAs($this->accountant)
            ->post(route('admin.payroll.adjustments.store', $payroll), [
                'type' => PayrollAdjustment::TYPE_BONUS,
                'amount' => 50,
                'note' => 'Accountant line',
            ])
            ->assertRedirect(route('admin.payroll.show', $payroll));

        $this->assertDatabaseHas('payroll_adjustments', [
            'payroll_record_id' => $payroll->id,
            'type' => PayrollAdjustment::TYPE_BONUS,
            'amount' => 50,
        ]);
    }

    public function test_accountant_has_full_advances_crud(): void
    {
        $this->actingAs($this->accountant)
            ->get(route('admin.advances.index'))
            ->assertOk();

        $this->actingAs($this->accountant)
            ->post(route('admin.advances.store'), [
                'user_id' => $this->employee->id,
                'amount' => 1000,
                'date_given' => '2026-08-01',
                'deduction_amount_monthly' => 250,
            ])
            ->assertRedirect();

        $advance = Advance::query()->where('user_id', $this->employee->id)->firstOrFail();

        $this->actingAs($this->accountant)
            ->put(route('admin.advances.update', $advance), [
                'amount' => 1200,
                'date_given' => '2026-08-01',
                'deduction_amount_monthly' => 300,
            ])
            ->assertRedirect();

        $this->assertSame(1200.0, (float) $advance->fresh()->amount);
    }

    public function test_accountant_blocked_from_admin_only_areas(): void
    {
        $this->actingAs($this->accountant);

        $this->get(route('admin.dashboard'))->assertForbidden();
        $this->get(route('admin.employees.index'))->assertForbidden();
        $this->get(route('admin.settings'))->assertForbidden();
        $this->get(route('admin.config.penalties'))->assertForbidden();
        $this->get(route('admin.reports.annual'))->assertForbidden();
        $this->get(route('team-leader.approvals'))->assertForbidden();
    }

    public function test_accountant_leave_request_has_null_team_leader_for_manager(): void
    {
        $this->actingAs($this->accountant)
            ->post(route('employee.requests.permissions.store'), [
                'type' => Permission::TYPE_LEAVE,
                'request_date' => now()->toDateString(),
                'from_time' => '10:00',
                'to_time' => '12:00',
                'reason' => 'Bank',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('permissions', [
            'user_id' => $this->accountant->id,
            'team_leader_id' => null,
            'status' => Permission::STATUS_PENDING,
        ]);

        $permission = Permission::query()->where('user_id', $this->accountant->id)->firstOrFail();

        $this->actingAs($this->manager)
            ->post(route('team-leader.approvals.permissions.approve', $permission))
            ->assertRedirect();

        $this->assertSame(Permission::STATUS_ACCEPTED, $permission->fresh()->status);
    }

    public function test_manager_can_create_accountant_without_team_leader(): void
    {
        $this->actingAs($this->manager)
            ->post(route('admin.employees.store'), [
                'name_ar' => 'محاسب جديد',
                'name_en' => 'New Accountant',
                'email' => 'new.accountant@hrpro.test',
                'password' => 'password123',
                'role' => User::ROLE_ACCOUNTANT,
                'currency' => 'EGP',
                'language_preference' => 'ar',
                'team_leader_id' => User::factory()->teamLeader()->create()->id,
                'shift_type_default' => 'FULL_TIME',
                'work_start_time' => '10:00',
                'work_end_time' => '18:00',
                'status' => User::STATUS_ACTIVE,
                'base_salary' => 5000,
                'profile' => [
                    'national_id' => null,
                    'birth_date' => null,
                    'address' => null,
                    'phone' => null,
                    'gender' => null,
                    'religion' => null,
                    'marital_status' => null,
                ],
                'contract' => [
                    'hire_date' => '2026-01-01',
                    'contract_start' => '2026-01-01',
                    'contract_end' => '2026-12-31',
                    'contract_type' => 'سنة',
                ],
            ])
            ->assertRedirect();

        $created = User::query()->where('email', 'new.accountant@hrpro.test')->firstOrFail();
        $this->assertSame(User::ROLE_ACCOUNTANT, $created->role);
        $this->assertNull($created->team_leader_id);
    }
}
