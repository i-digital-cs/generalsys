<?php

namespace Tests\Feature\Admin;

use App\Models\Attendance;
use App\Models\AttendanceDailyScore;
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
        $leader = User::factory()->teamLeader()->create();

        Attendance::query()->where('user_id', $employee->id)->update([
            'attendance_score' => 100,
        ]);

        $day = Attendance::query()->where('user_id', $employee->id)->first();
        AttendanceDailyScore::factory()->create([
            'attendance_id' => $day->id,
            'team_leader_id' => $leader->id,
            'scored_by' => $leader->id,
            'performance_score' => 80,
            'behavior_score' => 90,
            'productivity_score' => 70,
            'interest_return_score' => 60,
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
        $this->assertSame($netBefore + 200, (float) $payroll->gross_pay - (float) $payroll->total_deductions);

        $this->actingAs($manager)
            ->get(route('admin.payroll.show', $payroll).'?tab=pay')
            ->assertOk()
            ->assertSee(__('payroll.adjustment_types.BONUS'), false)
            ->assertSee('Spot bonus', false);

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

    public function test_extra_days_zero_amount_fills_day_rate(): void
    {
        [$manager, $payroll] = $this->draftPayslip();

        $this->actingAs($manager)
            ->put(route('admin.payroll.update', $payroll), $this->countPayload([
                'extra_days' => 1,
                'extra_pay' => 0,
            ]))
            ->assertRedirect(route('admin.payroll.show', $payroll));

        $payroll->refresh();
        $this->assertSame(1, (int) $payroll->extra_days);
        $this->assertEqualsWithDelta(166.67, (float) $payroll->extra_pay, 0.01);
    }

    public function test_changing_extra_days_recalculates_amount_even_if_amount_was_set(): void
    {
        [$manager, $payroll] = $this->draftPayslip();

        $this->actingAs($manager)
            ->put(route('admin.payroll.update', $payroll), $this->countPayload([
                'extra_days' => 1,
                'extra_pay' => 166.67,
            ]))
            ->assertRedirect();

        $this->actingAs($manager)
            ->put(route('admin.payroll.update', $payroll), $this->countPayload([
                'extra_days' => 2,
                'extra_pay' => 166.67, // leftover from previous day — must be ignored
            ]))
            ->assertRedirect();

        $payroll->refresh();
        $this->assertSame(2, (int) $payroll->extra_days);
        $this->assertEqualsWithDelta(333.33, (float) $payroll->extra_pay, 0.01);
    }

    public function test_manual_extra_pay_amount_is_saved(): void
    {
        [$manager, $payroll] = $this->draftPayslip();

        $this->actingAs($manager)
            ->put(route('admin.payroll.update', $payroll), $this->countPayload([
                'extra_days' => 0,
                'extra_pay' => 166.67,
            ]))
            ->assertRedirect(route('admin.payroll.show', $payroll));

        $payroll->refresh();
        $this->assertEqualsWithDelta(166.67, (float) $payroll->extra_pay, 0.01);
        $this->assertGreaterThanOrEqual(166.67, (float) $payroll->gross_pay);
    }

    public function test_overtime_is_hours_times_rate_times_multiplier(): void
    {
        [$manager, $payroll] = $this->draftPayslip();

        $this->actingAs($manager)
            ->put(route('admin.payroll.update', $payroll), $this->countPayload([
                'overtime_hours' => 8,
                'overtime_multiplier' => 1.5,
            ]))
            ->assertRedirect(route('admin.payroll.show', $payroll));

        $payroll->refresh();
        // 8 × (5000/30/8) × 1.5 = 250
        $this->assertEqualsWithDelta(250.0, (float) $payroll->overtime_pay, 0.01);
        $this->assertEqualsWithDelta(1.5, (float) $payroll->overtime_multiplier, 0.01);
        $this->assertEqualsWithDelta(0.0, (float) $payroll->extra_shift_hours, 0.01);
    }

    public function test_save_counts_persists_after_reopening_draft(): void
    {
        [$manager, $payroll] = $this->draftPayslip();

        $this->actingAs($manager)
            ->put(route('admin.payroll.update', $payroll), $this->countPayload([
                'full_time_days' => 18,
                'rest_days' => 6,
            ]))
            ->assertRedirect(route('admin.payroll.show', $payroll));

        $this->actingAs($manager)
            ->get(route('admin.payroll.show', $payroll))
            ->assertOk();

        $payroll->refresh();
        $this->assertSame(18, (int) $payroll->full_time_days);
        $this->assertSame(6, (int) $payroll->rest_days);
        $this->assertEqualsWithDelta(3000.0, (float) $payroll->full_time_pay, 0.01); // 5000/30*18
        $this->assertEqualsWithDelta(1000.0, (float) $payroll->rest_day_pay, 0.01); // 5000/30*6
    }

    public function test_admin_can_edit_late_minutes_and_suggestion_is_shown(): void
    {
        [$manager, $payroll] = $this->draftPayslip();

        Attendance::query()
            ->where('user_id', $payroll->user_id)
            ->whereDate('date', '2026-06-01')
            ->update([
                'clock_in' => '2026-06-01 10:30:00',
                'clock_out' => '2026-06-01 18:00:00',
                'late_minutes' => 0,
                'adjusted_late_minutes' => null,
            ]);

        $this->actingAs($manager)
            ->put(route('admin.payroll.update', $payroll), $this->countPayload([
                'late_minutes_total' => 15,
            ]))
            ->assertRedirect(route('admin.payroll.show', $payroll));

        // Pre-Oct: suggestion = exact = stored late (0 after update), not punch raw.
        // Draft still holds manual 15 → Apply suggested button visible.
        $this->actingAs($manager)
            ->get(route('admin.payroll.show', $payroll))
            ->assertOk()
            ->assertSee(__('payroll.field_help.late_minutes_apply_suggested'), false)
            ->assertViewHas('suggestedLateMinutes', 0)
            ->assertSee('hp-apply-suggested-late-'.$payroll->id, false);

        $payroll->refresh();
        $this->assertSame(15, (int) $payroll->late_minutes_total);
    }

    public function test_refresh_from_attendance_overwrites_manual_counts(): void
    {
        [$manager, $payroll] = $this->draftPayslip();
        $payroll->update([
            'full_time_days' => 2,
            'full_time_pay' => 333.33,
            'hours_deduction' => 8,
            'gross_pay' => 333.33,
            'net_pay' => 166.67,
        ]);

        $this->actingAs($manager)
            ->post(route('admin.payroll.refresh-attendance', $payroll))
            ->assertRedirect(route('admin.payroll.show', $payroll))
            ->assertSessionHas('success', __('payroll.flash.refreshed_attendance'));

        $payroll->refresh();
        $this->assertSame(30, (int) $payroll->full_time_days);
        $this->assertEqualsWithDelta(0.0, (float) $payroll->hours_deduction, 0.01);
        $this->assertEqualsWithDelta(5000.0, (float) $payroll->gross_pay, 0.01);
    }

    public function test_draft_show_does_not_overwrite_manual_day_counts(): void
    {
        [$manager, $payroll] = $this->draftPayslip();
        $payroll->update([
            'full_time_days' => 2,
            'rest_days' => 4,
            'full_time_pay' => 333.33,
            'rest_day_pay' => 666.67,
            'hours_deduction' => 8,
            'gross_pay' => 1000.0,
            'net_pay' => 833.33,
        ]);

        $this->actingAs($manager)
            ->get(route('admin.payroll.show', $payroll))
            ->assertOk()
            ->assertSee(__('payroll.counts_out_of_sync_title'), false)
            ->assertSee(__('payroll.refresh_attendance'), false);

        $payroll->refresh();
        $this->assertSame(2, (int) $payroll->full_time_days);
        $this->assertSame(4, (int) $payroll->rest_days);
    }

    public function test_refresh_from_attendance_pulls_extra_shift_hours_into_overtime(): void
    {
        [$manager, $payroll] = $this->draftPayslip();
        Attendance::factory()->create([
            'user_id' => $payroll->user_id,
            'date' => '2026-06-15',
            'shift_type' => Attendance::SHIFT_EXTRA,
            'planned_hours' => 1,
            'actual_hours' => 1,
            'status' => Attendance::STATUS_PRESENT,
            'late_minutes' => 0,
        ]);

        $this->actingAs($manager)
            ->post(route('admin.payroll.refresh-attendance', $payroll))
            ->assertRedirect();

        $payroll->refresh();
        $this->assertEqualsWithDelta(1.0, (float) $payroll->overtime_hours, 0.01);
        $this->assertEqualsWithDelta(0.0, (float) $payroll->extra_shift_hours, 0.01);
    }

    public function test_refresh_from_attendance_pulls_admin_approved_recommended_overtime(): void
    {
        [$manager, $payroll] = $this->draftPayslip();

        Attendance::query()
            ->where('user_id', $payroll->user_id)
            ->whereDate('date', '2026-06-16')
            ->update([
                'raw_actual_hours' => 10,
                'recommended_overtime_minutes' => 90,
                'overtime_tl_recommendation' => 'RECOMMEND',
                'overtime_admin_decision' => 'APPROVED',
            ]);

        Attendance::query()
            ->where('user_id', $payroll->user_id)
            ->whereDate('date', '2026-06-17')
            ->update([
                'raw_actual_hours' => 10,
                'recommended_overtime_minutes' => 60,
                'overtime_tl_recommendation' => 'RECOMMEND',
                'overtime_admin_decision' => 'PENDING',
            ]);

        $this->actingAs($manager)
            ->post(route('admin.payroll.refresh-attendance', $payroll))
            ->assertRedirect();

        $payroll->refresh();
        // Only Admin-APPROVED minutes (90) count — PENDING is ignored.
        $this->assertEqualsWithDelta(1.5, (float) $payroll->overtime_hours, 0.01);
    }

    public function test_draft_overtime_multiplier_one_is_straight_time(): void
    {
        [$manager, $payroll] = $this->draftPayslip();

        $this->actingAs($manager)
            ->put(route('admin.payroll.update', $payroll), $this->countPayload([
                'overtime_hours' => 8,
                'overtime_multiplier' => 1.0,
            ]))
            ->assertRedirect();

        $payroll->refresh();
        $this->assertEqualsWithDelta(166.67, (float) $payroll->overtime_pay, 0.01);
    }

    public function test_excel_deduction_counts_recalculate_money(): void
    {
        [$manager, $payroll] = $this->draftPayslip();

        $this->actingAs($manager)
            ->put(route('admin.payroll.update', $payroll), $this->countPayload([
                'excused_absence_days' => 1,
                'unexcused_absence_days' => 1,
                'hours_deduction' => 3,
            ]))
            ->assertRedirect(route('admin.payroll.show', $payroll));

        $payroll->refresh();
        $this->assertSame(1, (int) $payroll->excused_absence_days);
        $this->assertSame(1, (int) $payroll->unexcused_absence_days);
        $this->assertEqualsWithDelta(3.0, (float) $payroll->hours_deduction, 0.01);
        $this->assertEqualsWithDelta(166.67, (float) $payroll->excused_absence_penalty, 0.01);
        $this->assertEqualsWithDelta(500.0, (float) $payroll->unexcused_absence_penalty, 0.01);
        $this->assertEqualsWithDelta(62.50, (float) $payroll->hours_deduction_amount, 0.01);
    }

    public function test_other_deduction_is_not_labeled_as_advance(): void
    {
        [$manager, $payroll] = $this->draftPayslip();

        $this->actingAs($manager)
            ->post(route('admin.payroll.adjustments.store', $payroll), [
                'type' => PayrollAdjustment::TYPE_OTHER_DEDUCTION,
                'amount' => 1000,
                'note' => 'عدد ساعات الخصم',
            ])
            ->assertRedirect();

        $payroll->refresh();
        $this->assertEqualsWithDelta(0.0, (float) $payroll->advance_deductions, 0.01);
        $this->assertEqualsWithDelta(1000.0, (float) $payroll->total_deductions, 0.01);

        $this->actingAs($manager)
            ->get(route('admin.payroll.show', $payroll))
            ->assertOk()
            ->assertSee(__('payroll.adjustment_types.OTHER_DEDUCTION'), false)
            ->assertSee('1,000.00', false);
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
            'overtime_hours' => 0,
            'overtime_multiplier' => 1.5,
            'day_off_work_days' => 0,
            'official_holiday_work_days' => 0,
            'extra_days' => 0,
            'extra_pay' => 0,
            'hours_deduction' => 0,
            'excused_absence_days' => 0,
            'unexcused_absence_days' => 0,
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
