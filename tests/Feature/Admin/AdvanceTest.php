<?php

namespace Tests\Feature\Admin;

use App\Jobs\GeneratePayrollJob;
use App\Models\Advance;
use App\Models\Attendance;
use App\Models\PayrollRecord;
use App\Models\User;
use App\Services\Payroll\PayrollGenerator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * T-4.8 — Advances CRUD + payroll deduction on approve.
 */
class AdvanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_and_update_advance(): void
    {
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee()->create(['currency' => 'EGP']);

        $this->actingAs($manager)
            ->post(route('admin.advances.store'), [
                'user_id' => $employee->id,
                'amount' => 1000,
                'date_given' => '2026-06-01',
                'deduction_amount_monthly' => 250,
            ])
            ->assertRedirect(route('admin.advances.index'))
            ->assertSessionHas('success');

        $advance = Advance::query()->first();
        $this->assertNotNull($advance);
        $this->assertSame(1000.0, (float) $advance->remaining_amount);
        $this->assertSame(Advance::STATUS_ACTIVE, $advance->status);

        $this->actingAs($manager)
            ->put(route('admin.advances.update', $advance), [
                'amount' => 800,
                'date_given' => '2026-06-01',
                'deduction_amount_monthly' => 200,
            ])
            ->assertRedirect(route('admin.advances.index'));

        $this->assertSame(800.0, (float) $advance->fresh()->amount);
        $this->assertSame(800.0, (float) $advance->fresh()->remaining_amount);
    }

    public function test_team_leader_gets_403_on_advances(): void
    {
        $leader = User::factory()->teamLeader()->create();

        $this->actingAs($leader)
            ->get(route('admin.advances.index'))
            ->assertForbidden();
    }

    public function test_employee_sees_own_advances_only(): void
    {
        $employee = User::factory()->employee()->create();
        $other = User::factory()->employee()->create();

        Advance::factory()->create([
            'user_id' => $employee->id,
            'amount' => 400,
            'remaining_amount' => 400,
            'deduction_amount_monthly' => 100,
        ]);
        Advance::factory()->create([
            'user_id' => $other->id,
            'amount' => 999,
            'remaining_amount' => 999,
            'deduction_amount_monthly' => 99,
        ]);

        $this->actingAs($employee)
            ->get(route('employee.advances'))
            ->assertOk()
            ->assertSee('400.00', false)
            ->assertDontSee('999.00', false);
    }

    public function test_payroll_generate_pulls_advance_and_approve_decrements_remaining(): void
    {
        Queue::fake();

        $manager = User::factory()->manager()->create();
        $leader = User::factory()->teamLeader()->create(['base_salary' => 4000]);
        $employee = User::factory()->employee()->create([
            'team_leader_id' => $leader->id,
            'base_salary' => 5000,
        ]);

        $this->seedFullMonth($employee, 6, 2026);
        $this->seedFullMonth($leader, 6, 2026);

        $advance = Advance::factory()->create([
            'user_id' => $employee->id,
            'amount' => 500,
            'deduction_amount_monthly' => 100,
            'remaining_amount' => 500,
            'status' => Advance::STATUS_ACTIVE,
        ]);

        $this->actingAs($manager)
            ->post(route('admin.payroll.generate'), [
                'month' => 6,
                'year' => 2026,
                'team_leader_id' => $leader->id,
            ])
            ->assertRedirect();

        /** @var GeneratePayrollJob $job */
        $job = Queue::pushed(GeneratePayrollJob::class)->first();
        $job->handle(app(PayrollGenerator::class));

        $draft = PayrollRecord::query()
            ->where('user_id', $employee->id)
            ->where('month', 6)
            ->where('year', 2026)
            ->first();

        $this->assertNotNull($draft);
        $this->assertSame(100.0, (float) $draft->advance_deductions);
        $this->assertTrue($draft->adjustments()->where('type', 'ADVANCE')->exists());

        $this->actingAs($manager)
            ->put(route('admin.payroll.approve', $draft))
            ->assertRedirect();

        $advance->refresh();
        $this->assertSame(400.0, (float) $advance->remaining_amount);
        $this->assertSame(Advance::STATUS_ACTIVE, $advance->status);
    }

    public function test_approve_marks_advance_fully_deducted_when_remaining_cleared(): void
    {
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee()->create(['base_salary' => 5000]);

        $advance = Advance::factory()->create([
            'user_id' => $employee->id,
            'amount' => 100,
            'deduction_amount_monthly' => 100,
            'remaining_amount' => 100,
        ]);

        $payroll = PayrollRecord::factory()->create([
            'user_id' => $employee->id,
            'month' => 6,
            'year' => 2026,
            'status' => PayrollRecord::STATUS_DRAFT,
            'created_by' => $manager->id,
            'advance_deductions' => 100,
        ]);

        app(PayrollGenerator::class)->approve($payroll);

        $advance->refresh();
        $this->assertSame(0.0, (float) $advance->remaining_amount);
        $this->assertSame(Advance::STATUS_FULLY_DEDUCTED, $advance->status);
    }

    public function test_validation_rejects_monthly_above_amount(): void
    {
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee()->create();

        $this->actingAs($manager)
            ->post(route('admin.advances.store'), [
                'user_id' => $employee->id,
                'amount' => 100,
                'date_given' => '2026-06-01',
                'deduction_amount_monthly' => 200,
            ])
            ->assertSessionHasErrors('deduction_amount_monthly');
    }

    private function seedFullMonth(User $user, int $month, int $year): void
    {
        $days = Carbon::create($year, $month, 1)->daysInMonth;

        for ($day = 1; $day <= $days; $day++) {
            Attendance::factory()->create([
                'user_id' => $user->id,
                'date' => Carbon::create($year, $month, $day)->toDateString(),
                'shift_type' => Attendance::SHIFT_FULL_TIME,
                'planned_hours' => 8,
                'actual_hours' => 8,
                'status' => Attendance::STATUS_PRESENT,
                'late_minutes' => 0,
            ]);
        }
    }
}
