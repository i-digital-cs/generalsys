<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\PayrollRecord;
use App\Models\RestDay;
use App\Models\ShiftAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T-7.5 — ASSISTANT: company-wide HR ops including employee salary/rates;
 * no payroll module, no approvals/scores.
 */
class AssistantRoleTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $assistant;

    private User $leader;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->manager()->create();
        $this->assistant = User::factory()->assistant()->create([
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
            'base_salary' => 5000,
        ]);
        $this->leader = User::factory()->teamLeader()->create();
        $this->employee = User::factory()->employee()->create([
            'team_leader_id' => $this->leader->id,
            'base_salary' => 6000,
            'online_half_day_rate' => 100,
        ]);
        $this->employee->profile()->create(['employee_code' => 'EMP-A1']);
        $this->employee->contracts()->create([
            'hire_date' => '2024-01-01',
            'contract_start' => '2024-01-01',
            'contract_end' => '2026-01-01',
            'contract_type' => 'سنة',
            'is_active' => true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function employeePayload(array $overrides = []): array
    {
        return array_merge([
            'name_ar' => 'موظف المساعدة',
            'name_en' => 'Assistant Created',
            'email' => 'assistant.created@hrpro.test',
            'password' => 'password123',
            'role' => User::ROLE_EMPLOYEE,
            'currency' => 'EGP',
            'language_preference' => 'ar',
            'team_leader_id' => $this->leader->id,
            'default_shifts' => [
                ['shift_type' => 'FULL_TIME', 'start' => '10:00', 'end' => '18:00'],
                ['shift_type' => '', 'start' => '', 'end' => ''],
            ],
            'status' => 'ACTIVE',
            'base_salary' => 4500,
            'online_half_day_rate' => 80,
            'online_full_day_rate' => 160,
            'overtime_hourly_rate' => 40,
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
                'hire_date' => '2024-01-01',
                'contract_start' => '2024-01-01',
                'contract_end' => '2026-01-01',
                'contract_type' => 'سنة',
            ],
        ], $overrides);
    }

    public function test_assistant_has_no_team_leader_and_lands_on_admin_dashboard(): void
    {
        $this->assertNull($this->assistant->team_leader_id);
        $this->assertTrue($this->assistant->isAssistant());
        $this->assertTrue($this->assistant->canManageOperations());
        $this->assertTrue($this->assistant->canManageRates());
        $this->assertFalse($this->assistant->canAccessPayroll());

        $this->actingAs($this->assistant)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(__('dashboard.missing_reports.title'), false);
    }

    public function test_assistant_can_create_employee_with_salary(): void
    {
        $this->actingAs($this->assistant)
            ->get(route('admin.employees.create'))
            ->assertOk()
            ->assertSee('name="base_salary"', false)
            ->assertSee('name="online_half_day_rate"', false);

        $this->actingAs($this->assistant)
            ->post(route('admin.employees.store'), $this->employeePayload([
                'base_salary' => 5500,
                'online_half_day_rate' => 90,
                'online_full_day_rate' => 180,
                'overtime_hourly_rate' => 45,
            ]))
            ->assertRedirect();

        $created = User::where('email', 'assistant.created@hrpro.test')->firstOrFail();
        $this->assertTrue($created->isEmployee());
        $this->assertSame('5500.00', $created->base_salary);
        $this->assertSame('90.00', $created->online_half_day_rate);
        $this->assertSame('180.00', $created->online_full_day_rate);
        $this->assertSame('45.00', $created->overtime_hourly_rate);
    }

    public function test_assistant_cannot_create_accountant_or_assistant(): void
    {
        $this->actingAs($this->assistant)
            ->post(route('admin.employees.store'), $this->employeePayload([
                'email' => 'no.accountant@hrpro.test',
                'role' => User::ROLE_ACCOUNTANT,
            ]))
            ->assertSessionHasErrors('role');

        $this->actingAs($this->assistant)
            ->post(route('admin.employees.store'), $this->employeePayload([
                'email' => 'no.assistant@hrpro.test',
                'role' => User::ROLE_ASSISTANT,
            ]))
            ->assertSessionHasErrors('role');
    }

    public function test_assistant_can_edit_existing_salary(): void
    {
        $this->actingAs($this->assistant)
            ->get(route('admin.employees.edit', $this->employee))
            ->assertOk()
            ->assertSee('name="base_salary"', false);

        $this->actingAs($this->assistant)
            ->put(route('admin.employees.update', $this->employee), $this->employeePayload([
                'email' => $this->employee->email,
                'password' => '',
                'name_en' => 'Updated By Assistant',
                'base_salary' => 7200,
                'online_half_day_rate' => 110,
                'online_full_day_rate' => 220,
                'overtime_hourly_rate' => 55,
                'contract' => [
                    'hire_date' => '2024-01-01',
                    'contract_start' => '2024-01-01',
                    'contract_end' => '2026-01-01',
                    'contract_type' => 'سنة',
                ],
            ]))
            ->assertRedirect(route('admin.employees.edit', $this->employee));

        $this->employee->refresh();
        $this->assertSame('Updated By Assistant', $this->employee->name_en);
        $this->assertSame('7200.00', $this->employee->base_salary);
        $this->assertSame('110.00', $this->employee->online_half_day_rate);
        $this->assertSame('220.00', $this->employee->online_full_day_rate);
        $this->assertSame('55.00', $this->employee->overtime_hourly_rate);
    }

    public function test_assistant_is_blocked_from_payroll_and_decision_routes(): void
    {
        $payroll = PayrollRecord::factory()->create([
            'user_id' => $this->employee->id,
            'created_by' => $this->manager->id,
            'status' => PayrollRecord::STATUS_DRAFT,
        ]);

        $this->actingAs($this->assistant);

        $this->get(route('admin.settings'))->assertForbidden();
        $this->get(route('admin.payroll.index'))->assertForbidden();
        $this->get(route('admin.payroll.show', $payroll))->assertForbidden();
        $this->get(route('admin.advances.index'))->assertForbidden();
        $this->get(route('admin.config.penalties'))->assertForbidden();
        $this->get(route('admin.reports.annual'))->assertForbidden();
        $this->get(route('team-leader.approvals'))->assertForbidden();
        $this->get(route('team-leader.evaluations'))->assertForbidden();
    }

    public function test_assistant_can_assign_shifts_and_rest_days(): void
    {
        $this->actingAs($this->assistant)
            ->get(route('team-leader.shifts'))
            ->assertOk();

        $this->actingAs($this->assistant)
            ->post(route('team-leader.shifts.store'), [
                'user_id' => $this->employee->id,
                'date' => '2026-08-20',
                'shift_type' => ShiftAssignment::TYPE_FULL_TIME,
                'expected_start_time' => '10:00',
                'expected_end_time' => '18:00',
                'expected_hours' => 8,
            ])
            ->assertRedirect();

        $this->assertTrue(
            ShiftAssignment::query()
                ->where('user_id', $this->employee->id)
                ->whereDate('date', '2026-08-20')
                ->where('shift_type', ShiftAssignment::TYPE_FULL_TIME)
                ->exists()
        );

        $this->actingAs($this->assistant)
            ->get(route('team-leader.rest-days'))
            ->assertOk();

        $this->actingAs($this->assistant)
            ->post(route('team-leader.rest-days.store', $this->employee), [
                'dates' => ['2026-08-21'],
            ])
            ->assertRedirect();

        $this->assertTrue(
            RestDay::query()
                ->where('user_id', $this->employee->id)
                ->whereDate('date', '2026-08-21')
                ->exists()
        );
    }

    public function test_assistant_can_view_reports_but_not_reply_or_score(): void
    {
        $attendance = Attendance::factory()->create([
            'user_id' => $this->employee->id,
            'date' => '2026-08-18',
            'daily_report' => 'Did the work',
            'clock_in' => '2026-08-18 10:00:00',
            'clock_out' => '2026-08-18 18:00:00',
        ]);

        $this->actingAs($this->assistant)
            ->get(route('team-leader.reports'))
            ->assertOk();

        $this->actingAs($this->assistant)
            ->get(route('team-leader.reports.show', $attendance))
            ->assertOk()
            ->assertSee('Did the work')
            ->assertDontSee('name="team_leader_response"', false)
            ->assertDontSee(__('evaluation.save_scores'));

        $this->actingAs($this->assistant)
            ->post(route('team-leader.reports.respond', $attendance), [
                'team_leader_response' => 'Nope',
            ])
            ->assertForbidden();

        $this->actingAs($this->assistant)
            ->put(route('team-leader.reports.scores', $attendance), [
                'performance_score' => 90,
                'behavior_score' => 90,
                'productivity_score' => 90,
                'interest_return_score' => 90,
            ])
            ->assertForbidden();
    }

    public function test_assistant_self_service_clock_is_allowed(): void
    {
        $this->actingAs($this->assistant)
            ->get(route('employee.clock'))
            ->assertOk();
    }

    public function test_manager_can_create_assistant_role(): void
    {
        $this->actingAs($this->manager)
            ->post(route('admin.employees.store'), $this->employeePayload([
                'email' => 'new.assistant@hrpro.test',
                'name_en' => 'New Assistant',
                'role' => User::ROLE_ASSISTANT,
                'team_leader_id' => $this->leader->id,
                'base_salary' => 4000,
                'online_half_day_rate' => 50,
                'online_full_day_rate' => 100,
                'overtime_hourly_rate' => 30,
            ]))
            ->assertRedirect();

        $created = User::where('email', 'new.assistant@hrpro.test')->firstOrFail();
        $this->assertSame(User::ROLE_ASSISTANT, $created->role);
        $this->assertNull($created->team_leader_id);
    }
}
