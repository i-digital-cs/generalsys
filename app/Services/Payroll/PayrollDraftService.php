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

        return $this->recalculate($record->fresh(['adjustments', 'user']));
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

            $this->recalculate($record->fresh(['adjustments', 'user']));

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
            $this->recalculate($record->fresh(['adjustments', 'user']));
        });
    }

    public function recalculate(PayrollRecord $record): PayrollRecord
    {
        $this->assertDraft($record);
        $record->loadMissing(['user', 'adjustments']);
        $user = $record->user;
        if ($user === null) {
            throw new InvalidArgumentException('Payroll record has no employee.');
        }

        $live = $this->aggregator->aggregate($user, (int) $record->month, (int) $record->year);
        $this->syncAdvanceAdjustments($record, $live['adjustments'] ?? [], (int) ($record->created_by ?: $user->id));

        // Rest Days screen is source of truth for rest_days on drafts.
        $record->rest_days = (int) ($live['rest_days'] ?? $record->rest_days);
        $record->save();

        $record->load('adjustments');

        $half = $user->online_half_day_rate;
        $full = $user->online_full_day_rate;
        $ot = $user->overtime_hourly_rate;

        $adjustments = $record->adjustments->map(fn (PayrollAdjustment $row) => [
            'type' => $row->type,
            'amount' => (float) $row->amount,
        ])->all();

        $result = $this->calculator->calculate(new PayrollCalculationInput(
            baseSalary: (float) $user->base_salary,
            currency: $user->currency ?: ($record->currency ?: 'EGP'),
            fullTimeDays: (int) $record->full_time_days,
            onlineHalfDays: (int) $record->online_half_days,
            onlineFullDays: (int) $record->online_full_days,
            officialHolidayWorkDays: (int) $record->official_holiday_work_days,
            excusedAbsenceDays: (int) $record->excused_absence_days,
            unexcusedAbsenceDays: (int) $record->unexcused_absence_days,
            hoursDeduction: (float) $record->hours_deduction,
            lateMinutesTotal: (int) $record->late_minutes_total,
            leavePermissionMinutes: (int) $live['leave_permission_minutes'],
            latePermissionMinutes: (int) $live['late_permission_minutes'],
            forcedPermissionPenaltyBase: (float) $live['forced_permission_penalty_base'],
            fridayFullTimeDays: (int) $record->friday_work_days,
            fridayOnlineDays: 0,
            dayOffFullTimeDays: (int) $record->day_off_work_days,
            dayOffOnlineDays: 0,
            overtimeHours: (float) $record->overtime_hours,
            extraShiftHours: (float) $record->extra_shift_hours,
            overtimeMultiplier: ((float) $record->overtime_multiplier > 0)
                ? (float) $record->overtime_multiplier
                : null,
            extraDays: (int) $record->extra_days,
            extraPay: (float) $record->extra_pay,
            onlineHalfDayRate: ((float) $half > 0) ? (float) $half : null,
            onlineFullDayRate: ((float) $full > 0) ? (float) $full : null,
            overtimeHourlyRate: ((float) $ot > 0) ? (float) $ot : null,
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
            ],
        ));

        return $record->fresh(['adjustments', 'user']);
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
