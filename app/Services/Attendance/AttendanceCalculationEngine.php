<?php

namespace App\Services\Attendance;

use App\Models\Attendance;
use App\Models\ShiftAssignment;
use Illuminate\Support\Collection;

/**
 * Authoritative attendance calculation façade (SSOT).
 *
 * Persistence: {@see AttendanceTimeCalculator::recalculate}
 * Display / classification: {@see AttendanceTimeCalculator::summarize} → {@see AttendanceTimeSummary}
 * Grace thresholds: {@see AttendanceGracePolicy}
 * Windows / planned hours: {@see ShiftWindowResolver}, {@see ShiftConfig}
 * Payroll late total: {@see AttendanceService::exactLateMinutes}
 *
 * Controllers, Blade, dashboards, and exports must consume this engine (or summary
 * components fed by it) — never re-implement late/early/deficit/grace math.
 */
final class AttendanceCalculationEngine
{
    public function __construct(
        private readonly AttendanceTimeCalculator $calculator,
        private readonly AttendanceService $attendance,
        private readonly AttendanceGracePolicy $grace,
    ) {}

    public function forAttendance(
        Attendance $attendance,
        ?ShiftAssignment $assignment = null,
    ): AttendanceCalculationResult {
        $attendance->loadMissing(['segments', 'user', 'gaps']);
        $summary = $this->calculator->summarize($attendance, $this->attendance, $assignment);
        $workday = $attendance->date?->toDateString() ?? '';

        return new AttendanceCalculationResult(
            attendanceId: (int) $attendance->id,
            userId: (int) $attendance->user_id,
            businessDate: $workday,
            shiftType: (string) $attendance->shift_type,
            checkoutProvenance: CheckoutProvenance::forAttendance($attendance),
            summary: $summary,
            policyVersion: AttendanceCalculationResult::POLICY_VERSION,
            gracePolicyActive: $this->grace->isActiveForAttendanceDate($workday),
            status: $attendance->status !== null ? (string) $attendance->status : null,
            exactLateMinutes: $this->attendance->exactLateMinutes($attendance),
            exactEarlyMinutes: $this->attendance->exactEarlyMinutes($attendance),
        );
    }

    /**
     * @param  Collection<int, Attendance>  $rows
     * @return Collection<int, AttendanceCalculationResult>
     */
    public function forMany(Collection $rows): Collection
    {
        return $rows->map(fn (Attendance $row) => $this->forAttendance($row));
    }

    /** Date-aware counted late (override wins). Payroll / history / dashboards. */
    public function exactLateMinutes(Attendance $attendance): int
    {
        return $this->attendance->exactLateMinutes($attendance);
    }

    /** Date-aware counted early departure (stored early before grace effective date). */
    public function exactEarlyMinutes(Attendance $attendance): int
    {
        return $this->attendance->exactEarlyMinutes($attendance);
    }

    public function rawLateMinutes(Attendance $attendance): int
    {
        return $this->attendance->rawLateMinutes($attendance);
    }

    public function rawEarlyMinutes(Attendance $attendance): int
    {
        return $this->attendance->rawEarlyMinutes($attendance);
    }

    /**
     * @param  Collection<int, Attendance>  $rows
     */
    public function sumExactLateMinutes(Collection $rows): int
    {
        $this->attendance->warmScheduledAssignments($rows);

        return (int) $rows->sum(fn (Attendance $row) => $this->exactLateMinutes($row));
    }

    /**
     * @param  Collection<int, Attendance>  $rows
     */
    public function sumExactEarlyMinutes(Collection $rows): int
    {
        $this->attendance->warmScheduledAssignments($rows);

        return (int) $rows->sum(fn (Attendance $row) => $this->exactEarlyMinutes($row));
    }

    /**
     * Aggregate duration balance for multiple sessions on one workday (history kind).
     *
     * @param  Collection<int, Attendance>  $rows
     * @return array<string, int|string|bool>|null
     */
    public function dayDurationBalance(Collection $rows, float $plannedHours, string $workday): ?array
    {
        if ($rows->isEmpty() || ! $this->grace->isActiveForAttendanceDate($workday)) {
            return null;
        }

        $this->attendance->warmScheduledAssignments($rows);

        $rawHours = round((float) $rows->sum(
            fn (Attendance $row) => (float) ($row->raw_actual_hours ?? $row->actual_hours)
        ), 2);

        return $this->grace->durationBalance(
            AttendanceCalculationResult::minutesFromHours($plannedHours),
            AttendanceCalculationResult::minutesFromHours($rawHours),
            $this->sumRawLateMinutes($rows),
            $this->sumRawEarlyMinutes($rows),
            $workday,
        );
    }

    /**
     * @param  Collection<int, Attendance>  $rows
     */
    public function sumRawLateMinutes(Collection $rows): int
    {
        $this->attendance->warmScheduledAssignments($rows);

        return (int) $rows->sum(fn (Attendance $row) => $this->rawLateMinutes($row));
    }

    /**
     * @param  Collection<int, Attendance>  $rows
     */
    public function sumRawEarlyMinutes(Collection $rows): int
    {
        $this->attendance->warmScheduledAssignments($rows);

        return (int) $rows->sum(fn (Attendance $row) => $this->rawEarlyMinutes($row));
    }
}
