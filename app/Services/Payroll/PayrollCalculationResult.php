<?php

namespace App\Services\Payroll;

/**
 * Calculated payslip components (maps to payroll_records columns).
 *
 * @phpstan-type Money float
 */
final class PayrollCalculationResult
{
    public function __construct(
        public readonly float $baseSalary,
        public readonly float $fullTimePay,
        public readonly float $onlineHalfPay,
        public readonly float $onlineFullPay,
        public readonly float $overtimePay,
        public readonly float $fridayBonus,
        public readonly float $dayOffBonus,
        public readonly float $restDayPay,
        public readonly float $officialHolidayBonus,
        public readonly float $extraPay,
        public readonly float $latePenalty,
        public readonly float $leavePermissionPenalty,
        public readonly float $latePermissionPenalty,
        public readonly float $excusedAbsencePenalty,
        public readonly float $unexcusedAbsencePenalty,
        public readonly float $forcedPenaltySurcharge,
        public readonly float $hoursDeductionAmount,
        public readonly float $advanceDeductions,
        public readonly float $commissionTotal,
        public readonly float $bonusTotal,
        public readonly float $grossPay,
        public readonly float $totalDeductions,
        public readonly float $netPay,
    ) {}

    /** @return array<string, float|int|string> */
    public function toPayrollAttributes(): array
    {
        return [
            'base_salary' => $this->round2($this->baseSalary),
            'full_time_pay' => $this->round2($this->fullTimePay),
            'online_half_pay' => $this->round2($this->onlineHalfPay),
            'online_full_pay' => $this->round2($this->onlineFullPay),
            'overtime_pay' => $this->round2($this->overtimePay),
            'friday_bonus' => $this->round2($this->fridayBonus),
            'day_off_bonus' => $this->round2($this->dayOffBonus),
            'rest_day_pay' => $this->round2($this->restDayPay),
            'official_holiday_bonus' => $this->round2($this->officialHolidayBonus),
            'extra_pay' => $this->round2($this->extraPay),
            'late_penalty' => $this->round2($this->latePenalty),
            'leave_permission_penalty' => $this->round2($this->leavePermissionPenalty),
            'late_permission_penalty' => $this->round2($this->latePermissionPenalty),
            'excused_absence_penalty' => $this->round2($this->excusedAbsencePenalty),
            'unexcused_absence_penalty' => $this->round2($this->unexcusedAbsencePenalty),
            'forced_penalty_surcharge' => $this->round2($this->forcedPenaltySurcharge),
            'hours_deduction_amount' => $this->round2($this->hoursDeductionAmount),
            'advance_deductions' => $this->round2($this->advanceDeductions),
            'gross_pay' => $this->round2($this->grossPay),
            'total_deductions' => $this->round2($this->totalDeductions),
            'net_pay' => $this->round2($this->netPay),
        ];
    }

    private function round2(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}
