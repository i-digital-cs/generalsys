<?php

namespace App\Services\Payroll;

use App\Models\PayrollAdjustment;
use App\Models\PenaltyRule;
use App\Services\Admin\SettingService;

/**
 * Pure payroll math (T-4.3) — docs/PAYROLL_CALCULATION.md §6–§9.
 *
 * Absence day multipliers and per-minute rates come from active penalty_rules
 * (config fallback). Pay multipliers come from config/payroll.php (draft OT
 * multiplier / extra pay override per employee).
 */
class PayrollCalculator
{
    public function __construct(
        private readonly PenaltyRuleService $rules,
        private readonly SettingService $settings,
    ) {}

    public function calculate(PayrollCalculationInput $input): PayrollCalculationResult
    {
        $divisor = (float) config('payroll.month_divisor', 30);
        $dayRate = $this->dayRate($input->baseSalary, $divisor);
        $autoPenalties = (bool) config('payroll.auto_penalties', false);

        $fullTimePay = $this->dayRatePay($input->baseSalary, $divisor, $input->fullTimeDays);
        $onlineHalfPay = $this->onlineHalfPay($input, $divisor);
        $onlineFullPay = $this->onlineFullPay($input, $divisor);

        $officialHolidayBonus = $this->multiplierPay(
            $dayRate,
            $input->officialHolidayWorkDays,
            $this->settings->multiplier('official_holiday_work'),
        );

        $fridayBonus = $this->fridayBonus($input, $dayRate);
        $dayOffBonus = $this->dayOffBonus($input, $dayRate);
        $overtimePay = $this->overtimePay($input);
        $extraPay = $this->extraPay($input, $dayRate);

        $excused = $this->absencePenalty(
            $input->currency,
            PenaltyRule::TYPE_EXCUSED_ABSENCE,
            $dayRate,
            $input->excusedAbsenceDays,
            1.0,
        );
        $unexcused = $this->absencePenalty(
            $input->currency,
            PenaltyRule::TYPE_UNEXCUSED_ABSENCE,
            $dayRate,
            $input->unexcusedAbsenceDays,
            3.0,
        );

        $latePenalty = $autoPenalties ? $this->minutePenalty(
            $input->currency,
            PenaltyRule::TYPE_LATE_ARRIVAL,
            $input->lateMinutesTotal,
        ) : 0.0;
        $leavePermissionPenalty = $autoPenalties ? $this->minutePenalty(
            $input->currency,
            PenaltyRule::TYPE_LEAVE_PERMISSION,
            $input->leavePermissionMinutes,
        ) : 0.0;
        $latePermissionPenalty = $autoPenalties ? $this->minutePenalty(
            $input->currency,
            PenaltyRule::TYPE_LATE_PERMISSION,
            $input->latePermissionMinutes,
        ) : 0.0;

        $forcedSurcharge = $autoPenalties ? $this->forcedSurcharge($input) : 0.0;

        // Excel «خصم ساعات» — always from hours count (like إضافي days).
        $hoursDeductionAmount = $this->hoursDeductionAmount(
            $input->baseSalary,
            $divisor,
            $input->hoursDeduction,
        );

        [$commissions, $bonuses, $advances, $otherDeductions] = $this->splitAdjustments($input->adjustments);

        $earnings = $fullTimePay
            + $onlineHalfPay
            + $onlineFullPay
            + $overtimePay
            + $extraPay
            + $fridayBonus
            + $dayOffBonus
            + $officialHolidayBonus
            + $commissions
            + $bonuses;

        $deductions = $excused
            + $unexcused
            + $latePenalty
            + $leavePermissionPenalty
            + $latePermissionPenalty
            + $forcedSurcharge
            + $hoursDeductionAmount
            + $advances
            + $otherDeductions;

        return new PayrollCalculationResult(
            baseSalary: $input->baseSalary,
            fullTimePay: $this->money($fullTimePay),
            onlineHalfPay: $this->money($onlineHalfPay),
            onlineFullPay: $this->money($onlineFullPay),
            overtimePay: $this->money($overtimePay),
            fridayBonus: $this->money($fridayBonus),
            dayOffBonus: $this->money($dayOffBonus),
            officialHolidayBonus: $this->money($officialHolidayBonus),
            extraPay: $this->money($extraPay),
            latePenalty: $this->money($latePenalty),
            leavePermissionPenalty: $this->money($leavePermissionPenalty),
            latePermissionPenalty: $this->money($latePermissionPenalty),
            excusedAbsencePenalty: $this->money($excused),
            unexcusedAbsencePenalty: $this->money($unexcused),
            forcedPenaltySurcharge: $this->money($forcedSurcharge),
            hoursDeductionAmount: $this->money($hoursDeductionAmount),
            advanceDeductions: $this->money($advances),
            commissionTotal: $this->money($commissions),
            bonusTotal: $this->money($bonuses),
            grossPay: $this->money($earnings),
            totalDeductions: $this->money($deductions),
            netPay: $this->money($earnings - $deductions),
        );
    }

    public function dayRate(float $baseSalary, ?float $divisor = null): float
    {
        $divisor ??= (float) config('payroll.month_divisor', 30);

        if ($divisor <= 0) {
            return 0.0;
        }

        return $baseSalary / $divisor;
    }

    public function dayRatePay(float $baseSalary, float $divisor, int $days): float
    {
        return $this->dayRate($baseSalary, $divisor) * $days;
    }

    public function multiplierPay(float $dayRate, int $days, float $multiplier): float
    {
        return $dayRate * $days * $multiplier;
    }

    private function money(float $value): float
    {
        return round($value, 2);
    }

    private function onlineHalfPay(PayrollCalculationInput $input, float $divisor): float
    {
        $rate = $this->dayRate($input->baseSalary, $divisor) / 2;

        return $rate * $input->onlineHalfDays;
    }

    private function onlineFullPay(PayrollCalculationInput $input, float $divisor): float
    {
        $rate = $this->dayRate($input->baseSalary, $divisor);

        return $rate * $input->onlineFullDays;
    }

    private function fridayBonus(PayrollCalculationInput $input, float $dayRate): float
    {
        $ft = $this->multiplierPay(
            $dayRate,
            $input->fridayFullTimeDays,
            $this->settings->multiplier('friday_full_time'),
        );

        $online = $dayRate
            * $input->fridayOnlineDays
            * $this->settings->multiplier('friday_online');

        return $ft + $online;
    }

    private function dayOffBonus(PayrollCalculationInput $input, float $dayRate): float
    {
        $ft = $this->multiplierPay(
            $dayRate,
            $input->dayOffFullTimeDays,
            $this->settings->multiplier('day_off_full_time'),
        );

        $online = $dayRate
            * $input->dayOffOnlineDays
            * $this->settings->multiplier('day_off_online');

        return $ft + $online;
    }

    private function overtimePay(PayrollCalculationInput $input): float
    {
        $hours = $input->overtimeHours + $input->extraShiftHours;
        if ($hours <= 0) {
            return 0.0;
        }

        $divisor = (float) config('payroll.month_divisor', 30);
        $planned = (float) config('payroll.planned_hours_full_time', 8);
        $hourly = $this->dayRate($input->baseSalary, $divisor) / max($planned, 1);

        $mult = $input->overtimeMultiplier;
        if ($mult === null || $mult <= 0) {
            $mult = $this->settings->multiplier('overtime');
        }

        return $hourly * $hours * $mult;
    }

    /**
     * Excel «إضافي»: days × day rate × multiplier when days > 0;
     * otherwise use the typed amount (manual override with days = 0).
     */
    private function extraPay(PayrollCalculationInput $input, float $dayRate): float
    {
        if ($input->extraDays > 0) {
            return $dayRate * $input->extraDays * $this->settings->multiplier('extra_pay');
        }

        return (float) $input->extraPay;
    }

    private function absencePenalty(
        string $currency,
        string $type,
        float $dayRate,
        int $days,
        float $defaultMultiplier,
    ): float {
        if ($days <= 0) {
            return 0.0;
        }

        $rule = $this->rules->findActive($type, $currency);
        $multiplier = $rule !== null
            ? (float) $rule->amount_per_unit
            : $defaultMultiplier;

        return $dayRate * $days * $multiplier;
    }

    private function minutePenalty(string $currency, string $type, int $minutes): float
    {
        if ($minutes <= 0) {
            return 0.0;
        }

        $rate = $this->perMinuteRate($currency, $type);

        return $minutes * $rate;
    }

    private function perMinuteRate(string $currency, string $type): float
    {
        $rule = $this->rules->findActive($type, $currency);
        if ($rule !== null) {
            return (float) $rule->amount_per_unit;
        }

        $rates = config('penalties.'.$currency, config('penalties.EGP'));

        return (float) ($rates[$type] ?? 0);
    }

    private function forcedSurcharge(PayrollCalculationInput $input): float
    {
        if ($input->forcedPermissionPenaltyBase <= 0) {
            return 0.0;
        }

        $rule = $this->rules->findActive(PenaltyRule::TYPE_LEAVE_PERMISSION, $input->currency)
            ?? $this->rules->findActive(PenaltyRule::TYPE_LATE_PERMISSION, $input->currency);

        $multiplier = $rule !== null
            ? (float) $rule->double_penalty_multiplier
            : (float) config('penalties.double_penalty_multiplier', 2.0);

        // Base is already the rejected amount; surcharge = base × (multiplier − 1)
        // so total forced cost = base × multiplier (Gate 4.3).
        return $input->forcedPermissionPenaltyBase * ($multiplier - 1);
    }

    private function hoursDeductionAmount(float $baseSalary, float $divisor, float $hours): float
    {
        if ($hours <= 0) {
            return 0.0;
        }

        $planned = (float) config('payroll.planned_hours_full_time', 8);
        $monthHours = $divisor * $planned;
        if ($monthHours <= 0) {
            return 0.0;
        }

        return ($baseSalary / $monthHours) * $hours;
    }

    /**
     * @param  list<array{type: string, amount: float}>  $adjustments
     * @return array{0: float, 1: float, 2: float, 3: float} commissions, bonuses, advances, otherDeductions
     */
    private function splitAdjustments(array $adjustments): array
    {
        $commissions = 0.0;
        $bonuses = 0.0;
        $advances = 0.0;
        $otherDeductions = 0.0;

        foreach ($adjustments as $row) {
            $amount = (float) ($row['amount'] ?? 0);
            $type = (string) ($row['type'] ?? '');

            if (in_array($type, [
                PayrollAdjustment::TYPE_DEPARTMENT_COMMISSION,
                PayrollAdjustment::TYPE_SALES_COMMISSION,
                PayrollAdjustment::TYPE_PROFIT_COMMISSION,
            ], true)) {
                $commissions += $amount;
            } elseif ($type === PayrollAdjustment::TYPE_BONUS) {
                $bonuses += $amount;
            } elseif ($type === PayrollAdjustment::TYPE_ADVANCE) {
                $advances += abs($amount);
            } elseif ($type === PayrollAdjustment::TYPE_OTHER_DEDUCTION) {
                $otherDeductions += abs($amount);
            }
        }

        return [$commissions, $bonuses, $advances, $otherDeductions];
    }
}
