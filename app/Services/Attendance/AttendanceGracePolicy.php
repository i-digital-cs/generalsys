<?php

namespace App\Services\Attendance;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Canonical late-arrival / early-departure grace + policy-adjusted duration balance.
 *
 * Effective date (attendance workday, not "today"): config attendance.grace_policy_effective_date
 * (default 2026-10-01). Before that date the new grace must NOT apply — callers must preserve
 * stored historical derived fields and must not reclassify via this policy.
 *
 * Grace is a threshold, not a deduction: ≤15 → counted 0; >15 → full raw minutes.
 */
final class AttendanceGracePolicy
{
    public const CLASS_DEFICIT = 'deficit';

    public const CLASS_BALANCED = 'balanced';

    public const CLASS_SURPLUS = 'surplus';

    public function effectiveDate(): string
    {
        $configured = (string) config('attendance.grace_policy_effective_date', '2026-10-01');

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $configured) !== 1) {
            return '2026-10-01';
        }

        return $configured;
    }

    /**
     * Whether the approved 15-minute grace policy applies to this attendance workday.
     * Missing/invalid date → false (fail safe: historical-compatible path).
     */
    public function isActiveForAttendanceDate(CarbonInterface|string|null $attendanceDate): bool
    {
        $ymd = $this->normalizeWorkday($attendanceDate);
        if ($ymd === null) {
            return false;
        }

        return $ymd >= $this->effectiveDate();
    }

    public function arrivalGraceMinutes(): int
    {
        return max(0, (int) config('attendance.arrival_grace_minutes', 15));
    }

    public function departureGraceMinutes(): int
    {
        return max(0, (int) config('attendance.departure_grace_minutes', 15));
    }

    /**
     * Counted late minutes when the new policy is active for the workday.
     * Callers must not use this to rewrite pre-effective-date stored values.
     */
    public function countedLateMinutes(int $rawLateMinutes, CarbonInterface|string|null $attendanceDate = null): int
    {
        if (! $this->isActiveForAttendanceDate($attendanceDate)) {
            return max(0, $rawLateMinutes);
        }

        $raw = max(0, $rawLateMinutes);

        return $raw <= $this->arrivalGraceMinutes() ? 0 : $raw;
    }

    public function countedEarlyMinutes(int $rawEarlyMinutes, CarbonInterface|string|null $attendanceDate = null): int
    {
        if (! $this->isActiveForAttendanceDate($attendanceDate)) {
            return max(0, $rawEarlyMinutes);
        }

        $raw = max(0, $rawEarlyMinutes);

        return $raw <= $this->departureGraceMinutes() ? 0 : $raw;
    }

    public function exemptArrivalMinutes(int $rawLateMinutes, CarbonInterface|string|null $attendanceDate = null): int
    {
        if (! $this->isActiveForAttendanceDate($attendanceDate)) {
            return 0;
        }

        $raw = max(0, $rawLateMinutes);

        return $raw <= $this->arrivalGraceMinutes() ? $raw : 0;
    }

    public function exemptDepartureMinutes(int $rawEarlyMinutes, CarbonInterface|string|null $attendanceDate = null): int
    {
        if (! $this->isActiveForAttendanceDate($attendanceDate)) {
            return 0;
        }

        $raw = max(0, $rawEarlyMinutes);

        return $raw <= $this->departureGraceMinutes() ? $raw : 0;
    }

    /**
     * @return array{
     *     classification: self::CLASS_*,
     *     planned_minutes: int,
     *     actual_worked_minutes: int,
     *     raw_difference_minutes: int,
     *     raw_late_minutes: int,
     *     raw_early_minutes: int,
     *     counted_late_minutes: int,
     *     counted_early_minutes: int,
     *     exempt_arrival_minutes: int,
     *     exempt_departure_minutes: int,
     *     counted_deficit_minutes: int,
     *     counted_surplus_minutes: int,
     *     grace_policy_active: bool
     * }
     */
    public function durationBalance(
        int $plannedMinutes,
        int $actualWorkedMinutes,
        int $rawLateMinutes,
        int $rawEarlyMinutes,
        CarbonInterface|string|null $attendanceDate = null,
    ): array {
        $planned = max(0, $plannedMinutes);
        $actual = max(0, $actualWorkedMinutes);
        $rawLate = max(0, $rawLateMinutes);
        $rawEarly = max(0, $rawEarlyMinutes);
        $rawDiff = $actual - $planned;
        $active = $this->isActiveForAttendanceDate($attendanceDate);

        $exemptArrival = $this->exemptArrivalMinutes($rawLate, $attendanceDate);
        $exemptDeparture = $this->exemptDepartureMinutes($rawEarly, $attendanceDate);
        $countedLate = $this->countedLateMinutes($rawLate, $attendanceDate);
        $countedEarly = $this->countedEarlyMinutes($rawEarly, $attendanceDate);

        $countedDeficit = 0;
        $countedSurplus = 0;

        if ($rawDiff >= 0) {
            $countedSurplus = $rawDiff;
        } else {
            $rawDeficit = -$rawDiff;
            $countedDeficit = max(0, $rawDeficit - $exemptArrival - $exemptDeparture);
        }

        $classification = match (true) {
            $countedDeficit > 0 => self::CLASS_DEFICIT,
            $countedSurplus > 0 => self::CLASS_SURPLUS,
            default => self::CLASS_BALANCED,
        };

        return [
            'classification' => $classification,
            'planned_minutes' => $planned,
            'actual_worked_minutes' => $actual,
            'raw_difference_minutes' => $rawDiff,
            'raw_late_minutes' => $rawLate,
            'raw_early_minutes' => $rawEarly,
            'counted_late_minutes' => $countedLate,
            'counted_early_minutes' => $countedEarly,
            'exempt_arrival_minutes' => $exemptArrival,
            'exempt_departure_minutes' => $exemptDeparture,
            'counted_deficit_minutes' => $countedDeficit,
            'counted_surplus_minutes' => $countedSurplus,
            'grace_policy_active' => $active,
        ];
    }

    private function normalizeWorkday(CarbonInterface|string|null $attendanceDate): ?string
    {
        if ($attendanceDate === null) {
            return null;
        }

        if ($attendanceDate instanceof CarbonInterface) {
            return $attendanceDate->toDateString();
        }

        $raw = trim($attendanceDate);
        if ($raw === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $raw, $m) === 1) {
            return substr($m[0], 0, 10);
        }

        try {
            return Carbon::parse($raw)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
