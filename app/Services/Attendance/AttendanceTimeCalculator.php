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

        $lateMinutes = $attendance->adjusted_late_minutes;
        if ($lateMinutes === null) {
            $lateMinutes = 0;
        }

        $earlyMinutes = 0;
        if ($openSegment === null && $lastClosed !== null && $lastClosed->clock_out !== null) {
            $expectedEnd = $attendanceService->expectedEndForAttendance($attendance, $assignment);
            if ($expectedEnd !== null && $lastClosed->clock_out->lt($expectedEnd)) {
                $earlyMinutes = max(0, (int) $lastClosed->clock_out->diffInMinutes($expectedEnd, false));
            }
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
            'late_minutes' => (int) ($lateMinutes ?? 0),
            'early_departure_minutes' => $earlyMinutes,
            'status' => $this->resolveStatus((int) ($lateMinutes ?? 0), $earlyMinutes),
            'attendance_score' => $openSegment === null
                ? $this->attendanceScore($credited, $planned)
                : null,
        ];

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

        $systemLate = 0;
        $firstClockIn = $first?->clock_in ?? $attendance->clock_in;
        if ($user !== null && $firstClockIn !== null) {
            $expected = $attendanceService->expectedStartFor(
                $user,
                $attendance->shift_type,
                $firstClockIn,
            );
            $systemLate = $expected !== null
                ? max(0, (int) $expected->diffInMinutes($firstClockIn, false))
                : 0;
        }

        $systemEarly = 0;
        $lastClockOut = $openSegment === null
            ? ($lastClosed?->clock_out ?? $attendance->clock_out)
            : null;
        if (! $isOpen && $lastClockOut !== null) {
            $expectedEnd = $attendanceService->expectedEndForAttendance($attendance, $assignment);
            if ($expectedEnd !== null && $lastClockOut->lt($expectedEnd)) {
                $systemEarly = max(0, (int) $lastClockOut->diffInMinutes($expectedEnd, false));
            }
        }

        $systemCredited = $this->systemCreditedHours($attendance, $rawHours, $planned);
        $creditedOverride = $this->hasManualCreditedHours($attendance, $rawHours);
        $lateOverride = $attendance->adjusted_late_minutes !== null;

        $gaps = $attendance->gaps->sortBy('gap_start')->values()->map(fn ($gap) => [
            'minutes' => (int) $gap->minutes,
            'start' => $gap->gap_start?->format('H:i'),
            'end' => $gap->gap_end?->format('H:i'),
            'tl_recommendation' => (string) $gap->tl_recommendation,
            'admin_decision' => (string) $gap->admin_decision,
        ])->all();

        $gapMinutesTotal = (int) array_sum(array_column($gaps, 'minutes'));
        $overtimeDeltaMinutes = (int) round($rawHours * 60) - (int) round($planned * 60);
        $systemSuggestedOvertimeMinutes = max(0, $overtimeDeltaMinutes);
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
            appliedLateMinutes: (int) $attendance->late_minutes,
            appliedEarlyMinutes: (int) $attendance->early_departure_minutes,
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
