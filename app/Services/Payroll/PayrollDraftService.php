<?php

namespace App\Services\Payroll;

use App\Models\PayrollAdjustment;
use App\Models\PayrollRecord;
use App\Models\User;
use App\Services\Admin\SettingService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Draft payslip review / edit / adjustments (T-4.5).
 */
class PayrollDraftService
{
    public function __construct(
        private readonly PayrollAggregator $aggregator,
        private readonly PayrollCalculator $calculator,
        private readonly SettingService $settings,
    ) {}

    /**
     * @param  array<string, int|float|string>  $counts
     */
    public function updateCounts(PayrollRecord $record, array $counts): PayrollRecord
    {
        $this->assertDraft($record);
        $record->loadMissing('user');
        $user = $record->user;
        if ($user === null) {
            throw new InvalidArgumentException('Payroll record has no employee.');
        }

        $extraDays = (int) ($counts['extra_days'] ?? $record->extra_days);
        $dayRate = $this->calculator->dayRate((float) $user->base_salary);
        // Days drive إضافي automatically; typed amount only when days = 0.
        if ($extraDays > 0) {
            $extraPay = round(
                $dayRate * $extraDays * $this->settings->multiplier('extra_pay'),
                2,
            );
        } else {
            $extraPay = array_key_exists('extra_pay', $counts)
                ? (float) $counts['extra_pay']
                : (float) $record->extra_pay;
        }

        $record->update([
            'full_time_days' => (int) ($counts['full_time_days'] ?? $record->full_time_days),
            'online_half_days' => (int) ($counts['online_half_days'] ?? $record->online_half_days),
            'online_full_days' => (int) ($counts['online_full_days'] ?? $record->online_full_days),
            'rest_days' => (int) ($counts['rest_days'] ?? $record->rest_days),
            // One OT hours field: EXTRA attendance is folded into overtime_hours.
            'extra_shift_hours' => 0,
            'overtime_hours' => (float) ($counts['overtime_hours'] ?? $record->overtime_hours),
            'overtime_multiplier' => (float) ($counts['overtime_multiplier'] ?? $record->overtime_multiplier),
            'friday_work_days' => (int) ($counts['friday_work_days'] ?? $record->friday_work_days),
            'day_off_work_days' => (int) ($counts['day_off_work_days'] ?? $record->day_off_work_days),
            'official_holiday_work_days' => (int) ($counts['official_holiday_work_days'] ?? $record->official_holiday_work_days),
            'extra_days' => $extraDays,
            'extra_pay' => $extraPay,
            'hours_deduction' => (float) ($counts['hours_deduction'] ?? $record->hours_deduction),
            'excused_absence_days' => (int) ($counts['excused_absence_days'] ?? $record->excused_absence_days),
            'unexcused_absence_days' => (int) ($counts['unexcused_absence_days'] ?? $record->unexcused_absence_days),
            'late_minutes_total' => (int) ($counts['late_minutes_total'] ?? $record->late_minutes_total),
        ]);

        // Keep the just-saved overrides (including rest_days). Advances still refresh.
        return $this->recalculate($record->fresh(['adjustments', 'user']), false);
    }

    /**
     * Re-pull worked days / hours / rest from live attendance into this draft.
     */
    public function refreshFromAttendance(PayrollRecord $record): PayrollRecord
    {
        $this->assertDraft($record);

        return $this->recalculate($record->fresh(['adjustments', 'user']), true);
    }

    /**
     * @param  array{type: string, amount: float|string, note?: string|null}  $data
     */
    public function addAdjustment(PayrollRecord $record, array $data, User $actor): PayrollAdjustment
    {
        $this->assertDraft($record);

        $adjustment = DB::transaction(function () use ($record, $data, $actor) {
            $row = PayrollAdjustment::query()->create([
                'payroll_record_id' => $record->id,
                'type' => $data['type'],
                'amount' => $data['amount'],
                'note' => $data['note'] ?? null,
                'created_by' => $actor->id,
            ]);

            $this->recalculate($record->fresh(['adjustments', 'user']), false);

            return $row;
        });

        return $adjustment;
    }

    public function deleteAdjustment(PayrollRecord $record, PayrollAdjustment $adjustment): void
    {
        $this->assertDraft($record);

        if ($adjustment->payroll_record_id !== $record->id) {
            throw new InvalidArgumentException('Adjustment does not belong to this payroll record.');
        }

        DB::transaction(function () use ($record, $adjustment) {
            $adjustment->delete();
            $this->recalculate($record->fresh(['adjustments', 'user']), false);
        });
    }

    /**
     * Recompute draft money. When $syncAttendanceCounts is true (Generate /
     * Refresh from attendance), day counts and hours shortfall are re-pulled
     * from attendance. Save Counts and draft show pass false so edits stick.
     */
    public function recalculate(PayrollRecord $record, bool $syncAttendanceCounts = true): PayrollRecord
    {
        $this->assertDraft($record);
        $record->loadMissing(['user', 'adjustments']);
        $user = $record->user;
        if ($user === null) {
            throw new InvalidArgumentException('Payroll record has no employee.');
        }

        $live = $this->aggregator->aggregate($user, (int) $record->month, (int) $record->year);
        $this->syncAdvanceAdjustments($record, $live['adjustments'] ?? [], (int) ($record->created_by ?: $user->id));

        if ($syncAttendanceCounts) {
            $this->applyAttendanceCounts($record, $live);
        }

        $record->load('adjustments');

        $adjustments = $record->adjustments->map(fn (PayrollAdjustment $row) => [
            'type' => $row->type,
            'amount' => (float) $row->amount,
        ])->all();

        $otMult = (float) $record->overtime_multiplier;
        $fridayFt = $syncAttendanceCounts
            ? (int) $live['friday_full_time_days']
            : (int) $record->friday_work_days;
        $fridayOnline = $syncAttendanceCounts ? (int) $live['friday_online_days'] : 0;
        $dayOffFt = $syncAttendanceCounts
            ? (int) $live['day_off_full_time_days']
            : (int) $record->day_off_work_days;
        $dayOffOnline = $syncAttendanceCounts ? (int) $live['day_off_online_days'] : 0;

        $result = $this->calculator->calculate(new PayrollCalculationInput(
            baseSalary: (float) $user->base_salary,
            currency: $user->currency ?: ($record->currency ?: 'EGP'),
            fullTimeDays: (int) $record->full_time_days,
            onlineHalfDays: (int) $record->online_half_days,
            onlineFullDays: (int) $record->online_full_days,
            restDays: (int) $record->rest_days,
            officialHolidayWorkDays: (int) $record->official_holiday_work_days,
            excusedAbsenceDays: (int) $record->excused_absence_days,
            unexcusedAbsenceDays: (int) $record->unexcused_absence_days,
            hoursDeduction: (float) $record->hours_deduction,
            lateMinutesTotal: (int) $record->late_minutes_total,
            leavePermissionMinutes: (int) $live['leave_permission_minutes'],
            latePermissionMinutes: (int) $live['late_permission_minutes'],
            forcedPermissionPenaltyBase: (float) $live['forced_permission_penalty_base'],
            fridayFullTimeDays: $fridayFt,
            fridayOnlineDays: $fridayOnline,
            dayOffFullTimeDays: $dayOffFt,
            dayOffOnlineDays: $dayOffOnline,
            overtimeHours: (float) $record->overtime_hours,
            extraShiftHours: (float) $record->extra_shift_hours,
            overtimeMultiplier: $otMult > 0 ? $otMult : null,
            extraDays: (int) $record->extra_days,
            extraPay: (float) $record->extra_pay,
            adjustments: $adjustments,
        ));

        $record->update(array_merge(
            $result->toPayrollAttributes(),
            [
                'currency' => $user->currency ?: ($record->currency ?: 'EGP'),
                'extra_days' => (int) $record->extra_days,
                'overtime_multiplier' => (float) $record->overtime_multiplier,
                'excused_absence_days' => (int) $record->excused_absence_days,
                'unexcused_absence_days' => (int) $record->unexcused_absence_days,
                'hours_deduction' => (float) $record->hours_deduction,
                'full_time_days' => (int) $record->full_time_days,
                'online_half_days' => (int) $record->online_half_days,
                'online_full_days' => (int) $record->online_full_days,
                'rest_days' => (int) $record->rest_days,
                'overtime_hours' => (float) $record->overtime_hours,
                'extra_shift_hours' => (float) $record->extra_shift_hours,
                'friday_work_days' => (int) $record->friday_work_days,
                'day_off_work_days' => (int) $record->day_off_work_days,
                'official_holiday_work_days' => (int) $record->official_holiday_work_days,
                'late_minutes_total' => (int) $record->late_minutes_total,
            ],
        ));

        return $record->fresh(['adjustments', 'user']);
    }

    /**
     * @param  array<string, mixed>  $live
     */
    private function applyAttendanceCounts(PayrollRecord $record, array $live): void
    {
        $record->full_time_days = (int) ($live['full_time_days'] ?? 0);
        $record->online_half_days = (int) ($live['online_half_days'] ?? 0);
        $record->online_full_days = (int) ($live['online_full_days'] ?? 0);
        $record->rest_days = (int) ($live['rest_days'] ?? 0);
        $record->excused_absence_days = (int) ($live['excused_absence_days'] ?? 0);
        $record->unexcused_absence_days = (int) ($live['unexcused_absence_days'] ?? 0);
        $record->official_holiday_work_days = (int) ($live['official_holiday_work_days'] ?? 0);
        $record->friday_work_days = (int) ($live['friday_work_days'] ?? 0);
        $record->day_off_work_days = (int) ($live['day_off_work_days'] ?? 0);
        $record->hours_deduction = (float) ($live['hours_deduction'] ?? 0);
        $record->late_minutes_total = (int) ($live['late_minutes_total'] ?? 0);
        $record->extra_shift_hours = 0;
        $record->overtime_hours = round(
            (float) ($live['overtime_hours'] ?? 0) + (float) ($live['extra_shift_hours'] ?? 0),
            2,
        );
        $record->save();
    }

    /**
     * Refresh ADVANCE lines from active advances; keep other manual adjustments.
     *
     * @param  list<array{type: string, amount: float}>  $advances
     */
    private function syncAdvanceAdjustments(PayrollRecord $record, array $advances, int $createdBy): void
    {
        $record->adjustments()
            ->where('type', PayrollAdjustment::TYPE_ADVANCE)
            ->delete();

        foreach ($advances as $row) {
            if (($row['type'] ?? '') !== PayrollAdjustment::TYPE_ADVANCE) {
                continue;
            }

            PayrollAdjustment::query()->create([
                'payroll_record_id' => $record->id,
                'type' => PayrollAdjustment::TYPE_ADVANCE,
                'amount' => $row['amount'],
                'note' => null,
                'created_by' => $createdBy,
            ]);
        }
    }

    private function assertDraft(PayrollRecord $record): void
    {
        if ($record->status !== PayrollRecord::STATUS_DRAFT) {
            throw new InvalidArgumentException('Only DRAFT payroll records can be edited.');
        }
    }
}
