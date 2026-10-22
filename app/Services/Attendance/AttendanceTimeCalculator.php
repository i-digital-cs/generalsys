<?php

namespace App\Services\Attendance;

use App\Models\Attendance;
use App\Models\AttendanceSegment;
use App\Models\ShiftAssignment;
use App\Models\User;

/**
 * Aggregate segment hours, late/early, credited cap, and attendance score (T-9.1).
 */
class AttendanceTimeCalculator
{
    public function __construct(
        private readonly ShiftConfig $shifts,
        private readonly AttendanceGracePolicy $grace,
    ) {}

    public function recalculate(
        Attendance $attendance,
        AttendanceService $attendanceService,
        ?ShiftAssignment $assignment = null,
    ): Attendance {
        $attendance->loadMissing(['segments', 'user']);
        $user = $attendance->user;
        if ($user === null) {
            return $attendance;
        }

        $segments = $attendance->segments->sortBy('sequence')->values();
        $openSegment = $segments->first(fn (AttendanceSegment $s) => $s->isOpen());

        $rawHours = round($segments->sum(fn (AttendanceSegment $s) => $s->workedMinutes()) / 60, 2);
        $planned = $this->livePlannedHours($attendance, $user, $assignment);
        $credited = $this->resolveCreditedHours($attendance, $rawHours, $planned);

        $first = $segments->first();
        $lastClosed = $segments->filter(fn (AttendanceSegment $s) => ! $s->isOpen())->last();

        $rawLate = 0;
        $firstClockIn = $first?->clock_in;
        if ($firstClockIn !== null) {
            $expectedStart = $attendanceService->expectedStartFor(
                $user,
                $attendance->shift_type,
                $firstClockIn,
            );
            if ($expectedStart !== null) {
                $rawLate = max(0, (int) $expectedStart->diffInMinutes($firstClockIn, false));
            }
        }

        // While the shift is still open, do not persist LATE status (flexible start).
        // Counted late/early are written on clock-out / recalculate when closed.
        $rawEarly = 0;
        if ($openSegment === null && $lastClosed !== null && $lastClosed->clock_out !== null) {
            $expectedEnd = $attendanceService->expectedEndForAttendance($attendance, $assignment);
            if ($expectedEnd !== null && $lastClosed->clock_out->lt($expectedEnd)) {
                $rawEarly = max(0, (int) $lastClosed->clock_out->diffInMinutes($expectedEnd, false));
            }
        }

        $workday = $attendance->date?->toDateString();
        $graceActive = $this->grace->isActiveForAttendanceDate($workday);
        // Already persisted closed day — further recalculates must not rewrite historical late/early.
        $alreadyPersistedClosed = $attendance->getOriginal('clock_out') !== null;

        if ($openSegment !== null) {
            $lateMinutes = 0;
            $earlyMinutes = 0;
            $status = Attendance::STATUS_PRESENT;
            $preserveHistoricalDerived = false;
        } elseif (! $graceActive && $alreadyPersistedClosed && $attendance->adjusted_late_minutes === null) {
            // Task 1.6 — freeze stored derived fields on settled/historical days.
            $preserveHistoricalDerived = true;
            $lateMinutes = (int) $attendance->late_minutes;
            $earlyMinutes = (int) $attendance->early_departure_minutes;
            $status = (string) $attendance->status;
        } elseif (! $graceActive) {
            // First close (or TL late override) before effective date: legacy semantics, no 15-min grace.
            $preserveHistoricalDerived = false;
            $lateMinutes = $attendance->adjusted_late_minutes !== null
                ? max(0, (int) $attendance->adjusted_late_minutes)
                : 0;
            $earlyMinutes = $alreadyPersistedClosed && $attendance->adjusted_late_minutes !== null
                ? (int) $attendance->early_departure_minutes
                : $rawEarly;
            $status = $this->resolveStatus($lateMinutes, $earlyMinutes);
        } else {
            $preserveHistoricalDerived = false;
            $lateMinutes = $attendance->adjusted_late_minutes !== null
                ? max(0, (int) $attendance->adjusted_late_minutes)
                : $this->grace->countedLateMinutes($rawLate, $workday);
            $earlyMinutes = $this->grace->countedEarlyMinutes($rawEarly, $workday);
            $status = $this->resolveStatus($lateMinutes, $earlyMinutes);
        }

        $payload = [
            'clock_in' => $first?->clock_in,
            'clock_out' => $openSegment === null ? $lastClosed?->clock_out : null,
            'clock_in_lat' => $first?->clock_in_lat,
            'clock_in_lng' => $first?->clock_in_lng,
            'clock_in_accuracy_m' => $first?->clock_in_accuracy_m,
            'clock_in_distance_m' => $first?->clock_in_distance_m,
            'clock_in_outside_geofence' => (bool) ($first?->clock_in_outside_geofence ?? false),
            'clock_out_lat' => $openSegment === null ? $lastClosed?->clock_out_lat : null,
            'clock_out_lng' => $openSegment === null ? $lastClosed?->clock_out_lng : null,
            'clock_out_accuracy_m' => $openSegment === null ? $lastClosed?->clock_out_accuracy_m : null,
            'clock_out_distance_m' => $openSegment === null ? $lastClosed?->clock_out_distance_m : null,
            'clock_out_outside_geofence' => $openSegment === null ? (bool) ($lastClosed?->clock_out_outside_geofence ?? false) : false,
            'planned_hours' => $planned,
            'raw_actual_hours' => $rawHours,
            'actual_hours' => $credited,
            'attendance_score' => $openSegment === null
                ? $this->attendanceScore($credited, $planned)
                : null,
        ];

        if (! $preserveHistoricalDerived) {
            $payload['late_minutes'] = $lateMinutes;
            $payload['early_departure_minutes'] = $earlyMinutes;
            $payload['status'] = $status;
        }

        if (! $this->hasManualCreditedHours($attendance, $rawHours) && $attendance->credited_hours !== null) {
            $payload['credited_hours'] = null;
        }

        $attendance->update($payload);

        return $attendance->refresh();
    }

    /**
     * Side-by-side system recommended vs payroll-credited values (R2).
     */
    public function summarize(
        Attendance $attendance,
        AttendanceService $attendanceService,
        ?ShiftAssignment $assignment = null,
    ): AttendanceTimeSummary {
        $attendance->loadMissing(['segments', 'user', 'gaps']);
        $user = $attendance->user;

        $segments = $attendance->segments->sortBy('sequence')->values();
        $openSegment = $segments->first(fn (AttendanceSegment $s) => $s->isOpen());
        $isOpen = $openSegment !== null
            || ($segments->isEmpty() && $attendance->clock_in !== null && $attendance->clock_out === null);
        $rawHours = round($segments->sum(fn (AttendanceSegment $s) => $s->workedMinutes()) / 60, 2);
        if ($segments->isEmpty() && $attendance->clock_in !== null && $attendance->clock_out !== null) {
            $rawHours = round((float) ($attendance->raw_actual_hours ?? $attendance->actual_hours), 2);
        }
        $planned = $user !== null
            ? $this->livePlannedHours($attendance, $user, $assignment)
            : (float) $attendance->planned_hours;

        $first = $segments->first();
        $lastClosed = $segments->filter(fn (AttendanceSegment $s) => ! $s->isOpen())->last();

        $rawLate = 0;
        $firstClockIn = $first?->clock_in ?? $attendance->clock_in;
        if ($user !== null && $firstClockIn !== null) {
            $expected = $attendanceService->expectedStartFor(
                $user,
                $attendance->shift_type,
                $firstClockIn,
            );
            $rawLate = $expected !== null
                ? max(0, (int) $expected->diffInMinutes($firstClockIn, false))
                : 0;
        }
        $workday = $attendance->date?->toDateString();
        $graceActive = $this->grace->isActiveForAttendanceDate($workday);
        $lateOverride = $attendance->adjusted_late_minutes !== null;

        $rawEarly = 0;
        $lastClockOut = $openSegment === null
            ? ($lastClosed?->clock_out ?? $attendance->clock_out)
            : null;
        if (! $isOpen && $lastClockOut !== null) {
            $expectedEnd = $attendanceService->expectedEndForAttendance($attendance, $assignment);
            if ($expectedEnd !== null && $lastClockOut->lt($expectedEnd)) {
                $rawEarly = max(0, (int) $lastClockOut->diffInMinutes($expectedEnd, false));
            }
        }

        // Pre-effective-date: use stored derived values (do not reclassify via new grace).
        if ($graceActive) {
            $systemLate = $this->grace->countedLateMinutes($rawLate, $workday);
            $systemEarly = $this->grace->countedEarlyMinutes($rawEarly, $workday);
        } else {
            $systemLate = max(0, (int) $attendance->late_minutes);
            $systemEarly = max(0, (int) $attendance->early_departure_minutes);
        }

        $systemCredited = $this->systemCreditedHours($attendance, $rawHours, $planned);
        $creditedOverride = $this->hasManualCreditedHours($attendance, $rawHours);

        $gaps = $attendance->gaps->sortBy('gap_start')->values()->map(fn ($gap) => [
            'minutes' => (int) $gap->minutes,
            'start' => $gap->gap_start?->format('H:i'),
            'end' => $gap->gap_end?->format('H:i'),
            'tl_recommendation' => (string) $gap->tl_recommendation,
            'admin_decision' => (string) $gap->admin_decision,
        ])->all();

        $gapMinutesTotal = (int) array_sum(array_column($gaps, 'minutes'));
        $plannedMinutes = (int) round($planned * 60);
        $actualMinutes = (int) round($rawHours * 60);
        $overtimeDeltaMinutes = $actualMinutes - $plannedMinutes;
        $systemSuggestedOvertimeMinutes = max(0, $overtimeDeltaMinutes);
        $durationBalance = $graceActive
            ? $this->grace->durationBalance(
                $plannedMinutes,
                $actualMinutes,
                $rawLate,
                $rawEarly,
                $workday,
            )
            : $this->historicalDurationBalanceFromStored(
                $plannedMinutes,
                $actualMinutes,
                $systemLate,
                $systemEarly,
            );
        $recommendedOvertimeMinutes = (int) $attendance->recommended_overtime_minutes;
        $tlRecommendation = (string) ($attendance->overtime_tl_recommendation ?? 'NONE');
        $adminDecision = (string) ($attendance->overtime_admin_decision ?? 'PENDING');
        $approvedOvertimeMinutes = $adminDecision === 'APPROVED'
            && $tlRecommendation === 'RECOMMEND'
            ? $recommendedOvertimeMinutes
            : 0;
        $creditedCapHours = round($planned + ($approvedOvertimeMinutes / 60), 2);

        $windowStart = null;
        $windowEnd = null;
        $clockInAt = null;
        $clockOutAt = null;
        $clockInWindowPosition = null;
        $clockOutWindowPosition = null;

        if ($user !== null) {
            $expectedStart = $attendanceService->expectedStartFor(
                $user,
                $attendance->shift_type,
                $attendance->date,
            );
            $expectedEnd = $attendanceService->expectedEndForAttendance($attendance, $assignment);
            if ($expectedStart !== null) {
                $windowStart = $expectedStart->format('H:i');
            }
            if ($expectedEnd !== null) {
                $windowEnd = $expectedEnd->format('H:i');
            }
            if ($firstClockIn !== null) {
                $clockInAt = $firstClockIn->format('H:i');
                $clockInWindowPosition = $attendanceService->outsideShiftWindowReason(
                    $user,
                    $attendance->shift_type,
                    $firstClockIn,
                ) ?? 'inside_window';
            }
            if (! $isOpen && $lastClockOut !== null) {
                $clockOutAt = $lastClockOut->format('H:i');
                $clockOutWindowPosition = $attendanceService->outsideShiftWindowReason(
                    $user,
                    $attendance->shift_type,
                    $lastClockOut,
                ) ?? 'inside_window';
            }
        }

        return new AttendanceTimeSummary(
            open: $isOpen,
            plannedHours: $planned,
            systemRawHours: $rawHours,
            systemCreditedHours: $systemCredited,
            systemLateMinutes: $systemLate,
            systemEarlyMinutes: $systemEarly,
            appliedRawHours: $rawHours,
            appliedCreditedHours: $isOpen ? 0.0 : round((float) $attendance->actual_hours, 2),
            // Counted late/early SSOT (override + date-aware grace) — not raw stored columns alone.
            appliedLateMinutes: $attendanceService->exactLateMinutes($attendance),
            appliedEarlyMinutes: $attendanceService->exactEarlyMinutes($attendance, $assignment),
            creditedOverridden: $creditedOverride,
            lateOverridden: $lateOverride,
            gapMinutesTotal: $gapMinutesTotal,
            gaps: $gaps,
            overtimeDeltaMinutes: $overtimeDeltaMinutes,
            systemSuggestedOvertimeMinutes: $systemSuggestedOvertimeMinutes,
            recommendedOvertimeMinutes: $recommendedOvertimeMinutes,
            approvedOvertimeMinutes: $approvedOvertimeMinutes,
            creditedCapHours: $creditedCapHours,
            overtimeTlRecommendation: $tlRecommendation,
            overtimeAdminDecision: $adminDecision,
            windowStart: $windowStart,
            windowEnd: $windowEnd,
            clockInAt: $clockInAt,
            clockOutAt: $clockOutAt,
            clockInWindowPosition: $clockInWindowPosition,
            clockOutWindowPosition: $clockOutWindowPosition,
            rawLateMinutes: $rawLate,
            rawEarlyMinutes: $rawEarly,
            durationBalance: $durationBalance,
        );
    }

    /**
     * Prefer live profile/assignment expected hours over a stale attendance.planned_hours
     * (e.g. row created when defaults were still 8h FT while profile is 21:00→09:00 = 12h).
     */
    public function livePlannedHours(
        Attendance $attendance,
        ?User $user = null,
        ?ShiftAssignment $assignment = null,
    ): float {
        if ($user !== null) {
            $attendance->setRelation('user', $user);
        }

        return $this->shifts->resolveForAttendance($attendance, $assignment);
    }

    private function systemCreditedHours(Attendance $attendance, float $rawHours, float $planned): float
    {
        $credited = min($rawHours, $planned);

        if (
            $attendance->overtime_admin_decision === 'APPROVED'
            && $attendance->overtime_tl_recommendation === 'RECOMMEND'
            && (int) $attendance->recommended_overtime_minutes > 0
        ) {
            $extra = round((int) $attendance->recommended_overtime_minutes / 60, 2);
            $credited = min($rawHours, $planned + $extra);
        }

        return round($credited, 2);
    }

    private function resolveCreditedHours(Attendance $attendance, float $rawHours, float $planned): float
    {
        if ($this->hasManualCreditedHours($attendance, $rawHours)) {
            return round((float) $attendance->credited_hours, 2);
        }

        $credited = min($rawHours, $planned);

        if (
            $attendance->overtime_admin_decision === 'APPROVED'
            && $attendance->overtime_tl_recommendation === 'RECOMMEND'
            && (int) $attendance->recommended_overtime_minutes > 0
        ) {
            $extra = round((int) $attendance->recommended_overtime_minutes / 60, 2);
            $credited = min($rawHours, $planned + $extra);
        }

        return round($credited, 2);
    }

    /**
     * Manual credited override from TL/Admin. Ignore stale 0.00 left from open-shift bugs
     * when the employee actually worked (raw > 0).
     */
    private function hasManualCreditedHours(Attendance $attendance, float $rawHours): bool
    {
        if ($attendance->credited_hours === null) {
            return false;
        }

        $manual = round((float) $attendance->credited_hours, 2);
        if ($manual === 0.0 && $rawHours > 0.0 && $attendance->overtime_tl_recommendation === 'NONE') {
            return false;
        }

        return true;
    }

    /**
     * Pre-grace-policy summary balance: physical duration delta + stored late/early labels.
     * Does not apply 15-minute exemptions (Task 1.6 historical path).
     *
     * @return array<string, int|string|bool>
     */
    private function historicalDurationBalanceFromStored(
        int $plannedMinutes,
        int $actualMinutes,
        int $storedLate,
        int $storedEarly,
    ): array {
        $planned = max(0, $plannedMinutes);
        $actual = max(0, $actualMinutes);
        $rawDiff = $actual - $planned;
        $countedDeficit = $rawDiff < 0 ? -$rawDiff : 0;
        $countedSurplus = $rawDiff > 0 ? $rawDiff : 0;

        $classification = match (true) {
            $countedDeficit > 0 => AttendanceGracePolicy::CLASS_DEFICIT,
            $countedSurplus > 0 => AttendanceGracePolicy::CLASS_SURPLUS,
            default => AttendanceGracePolicy::CLASS_BALANCED,
        };

        return [
            'classification' => $classification,
            'planned_minutes' => $planned,
            'actual_worked_minutes' => $actual,
            'raw_difference_minutes' => $rawDiff,
            'raw_late_minutes' => $storedLate,
            'raw_early_minutes' => $storedEarly,
            'counted_late_minutes' => $storedLate,
            'counted_early_minutes' => $storedEarly,
            'exempt_arrival_minutes' => 0,
            'exempt_departure_minutes' => 0,
            'counted_deficit_minutes' => $countedDeficit,
            'counted_surplus_minutes' => $countedSurplus,
            'grace_policy_active' => false,
        ];
    }

    private function resolveStatus(int $lateMinutes, int $earlyMinutes): string
    {
        if ($lateMinutes > 0) {
            return Attendance::STATUS_LATE;
        }
        if ($earlyMinutes > 0) {
            return Attendance::STATUS_EARLY_LEAVE;
        }

        return Attendance::STATUS_PRESENT;
    }

    private function attendanceScore(float $actualHours, float $plannedHours): ?float
    {
        if ($plannedHours <= 0) {
            return null;
        }

        return round(min(100, ($actualHours / $plannedHours) * 100), 2);
    }
}
