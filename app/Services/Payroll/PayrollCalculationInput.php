<?php

namespace App\Services\Payroll;

/**
 * Immutable inputs for one employee-month payroll calculation (T-4.3).
 */
final class PayrollCalculationInput
{
    /**
     * @param  list<array{type: string, amount: float}>  $adjustments  commissions / bonuses / advances
     */
    public function __construct(
        public readonly float $baseSalary,
        public readonly string $currency = 'EGP',
        public readonly int $fullTimeDays = 0,
        public readonly int $onlineHalfDays = 0,
        public readonly int $onlineFullDays = 0,
        public readonly int $officialHolidayWorkDays = 0,
        public readonly int $excusedAbsenceDays = 0,
        public readonly int $unexcusedAbsenceDays = 0,
        public readonly float $hoursDeduction = 0.0,
        public readonly int $lateMinutesTotal = 0,
        public readonly int $leavePermissionMinutes = 0,
        public readonly int $latePermissionMinutes = 0,
        public readonly float $forcedPermissionPenaltyBase = 0.0,
        public readonly int $fridayFullTimeDays = 0,
        public readonly int $fridayOnlineDays = 0,
        public readonly int $dayOffFullTimeDays = 0,
        public readonly int $dayOffOnlineDays = 0,
        public readonly float $overtimeHours = 0.0,
        public readonly float $extraShiftHours = 0.0,
        /** Null = use Admin Settings overtime multiplier. */
        public readonly ?float $overtimeMultiplier = null,
        public readonly int $extraDays = 0,
        public readonly float $extraPay = 0.0,
        public readonly ?float $onlineHalfDayRate = null,
        public readonly ?float $onlineFullDayRate = null,
        public readonly ?float $overtimeHourlyRate = null,
        public readonly array $adjustments = [],
    ) {}
}
