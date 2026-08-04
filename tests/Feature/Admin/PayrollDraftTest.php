<?php

namespace Tests\Feature\Admin;

use App\Models\Attendance;
use App\Models\PayrollAdjustment;
use App\Models\PayrollRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T-4.5 — Admin review / edit draft / approve (Gate 4.6).
 */
class PayrollDraftTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_sees_breakdown_and_can_edit_counts(): void
    {
        [$manager, $payroll] = $this->draftPayslip();

        $this->actingAs($manager)
            ->get(route('admin.payroll.show', $payroll))
            ->assertOk()
            ->assertSee(__('payroll.breakdown'), false)
            ->assertSee(__('payroll.approve'), false)
            ->assertSee(__('payroll.save_counts'), false)
            ->assertSee(__('payroll.evaluation.title'), false)
            ->assertSee(__('evaluation.fields.performance'), false)
            ->assertSee(__('evaluation.fields.interest_return'), false)
            ->assertSee(__('payroll.evaluation.monthly_total'), false);

        $this->actingAs($manager)
            ->put(route('admin.payroll.update', $payroll), $this->countPayload([
                'full_time_days' => 18,
            ]))
            ->assertRedirect(route('admin.payroll.show', $payroll))
            ->assertSessionHas('success');

        $payroll->refresh();
        $this->assertSame(18, (int) $payroll->full_time_days);
        $this->assertSame(3000.0, (float) $payroll->full_time_pay); // 5000/30*18
    }

    public function test_payroll_show_displays_five_monthly_score_averages(): void
    {
        [$manager, $payroll] = $this->draftPayslip();
        $employee = $payroll->user;

        Attendance::query()->where('user_id', $employee->id)->update([
            'performance_score' => 80,
            'behavior_score' => 90,
            'productivity_score' => 70,
            'interest_return_score' => 60,
            'attendance_score' => 100,
        ]);

        $this->actingAs($manager)
            ->get(route('admin.payroll.show', $payroll))
            ->assertOk()
            ->assertSee(__('payroll.evaluation.title'), false)
            ->assertSee(__('payroll.evaluation.monthly_total'), false)
            ->assertSee(__('payroll.evaluation.year_total'), false)
            ->assertSee(__('payroll.attendance_summary.title'), false)
            ->assertSee(__('payroll.hours.title'), false)
            ->assertSee('80.00%', false)
            ->assertSee('90.00%', false)
            ->assertSee('70.00%', false)
            ->assertSee('60.00%', false)
            ->assertSee('100.00%', false)
            ->assertSee('80.00%', false); // monthly total = 400/5
    }

    public function test_manager_can_add_and_remove_adjustment(): void
    {
        [$manager, $payroll] = $this->draftPayslip();
        $netBefore = (float) $payroll->net_pay;

        $this->actingAs($manager)
            ->post(route('admin.payroll.adjustments.store', $payroll), [
                'type' => PayrollAdjustment::TYPE_BONUS,
                'amount' => 200,
                'note' => 'Spot bonus',
            ])
            ->assertRedirect(route('admin.payroll.show', $payroll));

        $payroll->refresh();
        $this->assertSame(1, $payroll->adjustments()->count());
        $this->assertSame($netBefore + 200, (float) $payroll->net_pay);

        $adj = $payroll->adjustments()->first();

        $this->actingAs($manager)
            ->delete(route('admin.payroll.adjustments.destroy', [$payroll, $adj]))
            ->assertRedirect(route('admin.payroll.show', $payroll));

        $this->assertSame(0, $payroll->fresh()->adjustments()->count());
        $this->assertSame($netBefore, (float) $payroll->fresh()->net_pay);
    }

    public function test_approve_from_show_locks_edit(): void
    {
        [$manager, $payroll] = $this->draftPayslip();

        $this->actingAs($manager)
            ->put(route('admin.payroll.approve', $payroll))
            ->assertRedirect(route('admin.payroll.show', $payroll))
            ->assertSessionHas('success');

        $this->assertSame(PayrollRecord::STATUS_APPROVED, $payroll->fresh()->status);

        $this->actingAs($manager)
            ->get(route('admin.payroll.show', $payroll))
            ->assertOk()
            ->assertSee(__('payroll.locked_note'), false)
            ->assertDontSee(__('payroll.save_counts'), false);

        $this->actingAs($manager)
            ->put(route('admin.payroll.update', $payroll), $this->countPayload())
            ->assertForbidden();
    }

    public function test_team_leader_cannot_open_payroll_show(): void
    {
        [, $payroll] = $this->draftPayslip();
        $leader = User::factory()->teamLeader()->create();

        $this->actingAs($leader)
            ->get(route('admin.payroll.show', $payroll))
            ->assertForbidden();
    }

    public function test_cannot_edit_approved_via_adjustment(): void
    {
        [$manager, $payroll] = $this->draftPayslip();
        $payroll->update(['status' => PayrollRecord::STATUS_APPROVED]);

        $this->actingAs($manager)
            ->post(route('admin.payroll.adjustments.store', $payroll), [
                'type' => PayrollAdjustment::TYPE_BONUS,
                'amount' => 50,
            ])
            ->assertForbidden();
    }

    public function test_save_recalculate_refreshes_base_salary_from_employee(): void
    {
        [$manager, $payroll] = $this->draftPayslip();
        $payroll->update(['base_salary' => 0, 'gross_pay' => 0, 'net_pay' => 0, 'full_time_pay' => 0]);
        $payroll->user->update(['base_salary' => 6000]);

        $this->actingAs($manager)
            ->put(route('admin.payroll.update', $payroll), $this->countPayload([
                'full_time_days' => 20,
            ]))
            ->assertRedirect(route('admin.payroll.show', $payroll));

        $payroll->refresh();
        $this->assertSame(6000.0, (float) $payroll->base_salary);
        $this->assertSame(4000.0, (float) $payroll->full_time_pay); // 6000/30*20
    }

    /**
     * @return array{0: User, 1: PayrollRecord}
     */
    private function draftPayslip(): array
    {
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee()->create(['base_salary' => 5000]);
        $this->seedFullMonth($employee, 6, 2026);

        $payroll = PayrollRecord::factory()->create([
            'user_id' => $employee->id,
            'month' => 6,
            'year' => 2026,
            'currency' => 'EGP',
            'base_salary' => 5000,
            'full_time_days' => 20,
            'full_time_pay' => 3333.33,
            'gross_pay' => 3333.33,
            'total_deductions' => 0,
            'net_pay' => 3333.33,
            'status' => PayrollRecord::STATUS_DRAFT,
            'created_by' => $manager->id,
        ]);

        return [$manager, $payroll];
    }

    /** @param  array<string, int|float>  $overrides */
    private function countPayload(array $overrides = []): array
    {
        return array_merge([
            'full_time_days' => 20,
            'online_half_days' => 0,
            'online_full_days' => 0,
            'rest_days' => 0,
            'extra_shift_hours' => 0,
            'overtime_hours' => 0,
            'friday_work_days' => 0,
            'day_off_work_days' => 0,
            'official_holiday_work_days' => 0,
            'hours_deduction' => 0,
            'late_minutes_total' => 0,
        ], $overrides);
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
