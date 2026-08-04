<?php

namespace App\Services\Payroll;

use App\Models\PayrollAdjustment;
use App\Models\PenaltyRule;

/**
 * Pure payroll math (T-4.3) — docs/PAYROLL_CALCULATION.md §6–§9.
 *
 * Absence day multipliers and per-minute rates come from active penalty_rules
 * (config fallback). Month divisor default 30.
 */
class PayrollCalculator
{
    public function __construct(private readonly PenaltyRuleService $rules) {}

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
            (float) config('payroll.multipliers.official_holiday_work', 1.0),
        );

        $fridayBonus = $this->fridayBonus($input, $dayRate);
        $dayOffBonus = $this->dayOffBonus($input, $dayRate);
        $overtimePay = $this->overtimePay($input);

        $excused = $autoPenalties ? $this->absencePenalty(
            $input->currency,
            PenaltyRule::TYPE_EXCUSED_ABSENCE,
            $dayRate,
            $input->excusedAbsenceDays,
            1.0,
        ) : 0.0;
        $unexcused = $autoPenalties ? $this->absencePenalty(
            $input->currency,
            PenaltyRule::TYPE_UNEXCUSED_ABSENCE,
            $dayRate,
            $input->unexcusedAbsenceDays,
            3.0,
        ) : 0.0;

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

        $hoursDeductionAmount = $autoPenalties ? $this->hoursDeductionAmount(
            $input->baseSalary,
            $divisor,
            $input->hoursDeduction,
        ) : 0.0;

        [$commissions, $bonuses, $advances] = $this->splitAdjustments($input->adjustments);

        $earnings = $fullTimePay
            + $onlineHalfPay
            + $onlineFullPay
            + $overtimePay
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
            + $advances;

        return new PayrollCalculationResult(
            baseSalary: $input->baseSalary,
            fullTimePay: $this->money($fullTimePay),
            onlineHalfPay: $this->money($onlineHalfPay),
            onlineFullPay: $this->money($onlineFullPay),
            overtimePay: $this->money($overtimePay),
            fridayBonus: $this->money($fridayBonus),
            dayOffBonus: $this->money($dayOffBonus),
            officialHolidayBonus: $this->money($officialHolidayBonus),
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
        $rate = $input->onlineHalfDayRate;
        if ($rate === null) {
            $rate = $this->dayRate($input->baseSalary, $divisor) / 2;
        }

        return (float) $rate * $input->onlineHalfDays;
    }

    private function onlineFullPay(PayrollCalculationInput $input, float $divisor): float
    {
        $rate = $input->onlineFullDayRate;
        if ($rate === null) {
            $rate = $this->dayRate($input->baseSalary, $divisor);
        }

        return (float) $rate * $input->onlineFullDays;
    }

    private function fridayBonus(PayrollCalculationInput $input, float $dayRate): float
    {
        $ft = $this->multiplierPay(
            $dayRate,
            $input->fridayFullTimeDays,
            (float) config('payroll.multipliers.friday_full_time', 1.0),
        );

        $onlineRate = $input->onlineHalfDayRate
            ?? ($dayRate * (float) config('payroll.multipliers.friday_online', 0.5));

        $online = (float) $onlineRate * $input->fridayOnlineDays;

        return $ft + $online;
    }

    private function dayOffBonus(PayrollCalculationInput $input, float $dayRate): float
    {
        $ft = $this->multiplierPay(
            $dayRate,
            $input->dayOffFullTimeDays,
            (float) config('payroll.multipliers.day_off_full_time', 2.0),
        );

        $onlineUnit = $input->onlineFullDayRate ?? $dayRate;
        $online = (float) $onlineUnit
            * $input->dayOffOnlineDays
            * (float) config('payroll.multipliers.day_off_online', 1.5);

        return $ft + $online;
    }

    private function overtimePay(PayrollCalculationInput $input): float
    {
        $hours = $input->overtimeHours + $input->extraShiftHours;
        if ($hours <= 0) {
            return 0.0;
        }

        $hourly = $input->overtimeHourlyRate;
        if ($hourly === null) {
            $divisor = (float) config('payroll.month_divisor', 30);
            $planned = (float) config('payroll.planned_hours_full_time', 8);
            $hourly = $this->dayRate($input->baseSalary, $divisor) / max($planned, 1);
        }

        $mult = (float) config('payroll.multipliers.overtime', 1.5);

        return (float) $hourly * $hours * $mult;
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
     * @return array{0: float, 1: float, 2: float} commissions, bonuses, advances
     */
    private function splitAdjustments(array $adjustments): array
    {
        $commissions = 0.0;
        $bonuses = 0.0;
        $advances = 0.0;

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
            } elseif (in_array($type, [
                PayrollAdjustment::TYPE_ADVANCE,
                PayrollAdjustment::TYPE_OTHER_DEDUCTION,
            ], true)) {
                $advances += abs($amount);
            }
        }

        return [$commissions, $bonuses, $advances];
    }
}
