<?php

namespace App\Services\Payroll;

use App\Models\PayrollAdjustment;
use App\Models\PayrollRecord;
use App\Models\User;
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
    ) {}

    /**
     * @param  array<string, int|float|string>  $counts
     */
    public function updateCounts(PayrollRecord $record, array $counts): PayrollRecord
    {
        $this->assertDraft($record);

        $record->update([
            'full_time_days' => (int) ($counts['full_time_days'] ?? $record->full_time_days),
            'online_half_days' => (int) ($counts['online_half_days'] ?? $record->online_half_days),
            'online_full_days' => (int) ($counts['online_full_days'] ?? $record->online_full_days),
            'rest_days' => (int) ($counts['rest_days'] ?? $record->rest_days),
            'extra_shift_hours' => (float) ($counts['extra_shift_hours'] ?? $record->extra_shift_hours),
            'overtime_hours' => (float) ($counts['overtime_hours'] ?? $record->overtime_hours),
            'friday_work_days' => (int) ($counts['friday_work_days'] ?? $record->friday_work_days),
            'day_off_work_days' => (int) ($counts['day_off_work_days'] ?? $record->day_off_work_days),
            'official_holiday_work_days' => (int) ($counts['official_holiday_work_days'] ?? $record->official_holiday_work_days),
            'hours_deduction' => (float) ($counts['hours_deduction'] ?? $record->hours_deduction),
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
            excusedAbsenceDays: (int) $live['excused_absence_days'],
            unexcusedAbsenceDays: (int) $live['unexcused_absence_days'],
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
            onlineHalfDayRate: ((float) $half > 0) ? (float) $half : null,
            onlineFullDayRate: ((float) $full > 0) ? (float) $full : null,
            overtimeHourlyRate: ((float) $ot > 0) ? (float) $ot : null,
            adjustments: $adjustments,
        ));

        $record->update(array_merge(
            $result->toPayrollAttributes(),
            ['currency' => $user->currency ?: ($record->currency ?: 'EGP')],
        ));

        return $record->fresh(['adjustments', 'user']);
    }

    private function assertDraft(PayrollRecord $record): void
    {
        if ($record->status !== PayrollRecord::STATUS_DRAFT) {
            throw new InvalidArgumentException('Only DRAFT payroll records can be edited.');
        }
    }
}
