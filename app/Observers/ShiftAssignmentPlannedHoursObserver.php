<?php

namespace App\Observers;

use App\Models\Attendance;
use App\Models\ShiftAssignment;
use App\Services\Attendance\AttendanceService;
use App\Services\Attendance\AttendanceTimeCalculator;
use App\Services\Attendance\ShiftConfig;

/**
 * TL assignment expected_hours changes must refresh that day’s attendance cache.
 */
class ShiftAssignmentPlannedHoursObserver
{
    public function __construct(
        private readonly ShiftConfig $shifts,
        private readonly AttendanceTimeCalculator $calculator,
        private readonly AttendanceService $attendanceService,
    ) {}

    public function saved(ShiftAssignment $assignment): void
    {
        if ($assignment->status !== ShiftAssignment::STATUS_SCHEDULED) {
            return;
        }

        $rows = Attendance::query()
            ->with('user')
            ->where('user_id', $assignment->user_id)
            ->whereDate('date', $assignment->date)
            ->where('shift_type', $assignment->shift_type)
            ->get();

        foreach ($rows as $attendance) {
            $live = $this->shifts->resolveForAttendance($attendance, $assignment);
            if (abs($live - (float) ($attendance->getAttributes()['planned_hours'] ?? 0)) < 0.001) {
                continue;
            }
            $this->calculator->recalculate(
                $attendance->fresh(['segments', 'user']) ?? $attendance,
                $this->attendanceService,
                $assignment,
            );
        }
    }
}
