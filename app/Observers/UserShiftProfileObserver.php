<?php

namespace App\Observers;

use App\Models\Attendance;
use App\Models\User;
use App\Services\Attendance\AttendanceService;
use App\Services\Attendance\AttendanceTimeCalculator;
use App\Services\Attendance\ShiftConfig;

/**
 * When default shift windows / expected hours change, refresh attendance cache
 * so OT / payroll / portal stay aligned with ShiftConfig (SSOT).
 */
class UserShiftProfileObserver
{
    public function __construct(
        private readonly ShiftConfig $shifts,
        private readonly AttendanceTimeCalculator $calculator,
        private readonly AttendanceService $attendanceService,
    ) {}

    public function updated(User $user): void
    {
        if (! $user->wasChanged([
            'shift_types_default',
            'shift_type_default',
            'work_start_time',
            'work_end_time',
        ])) {
            return;
        }

        Attendance::query()
            ->where('user_id', $user->id)
            ->where('date', '>=', now()->subMonths(3)->toDateString())
            ->with('user')
            ->orderBy('id')
            ->chunkById(50, function ($rows) use ($user): void {
                foreach ($rows as $attendance) {
                    $attendance->setRelation('user', $user);
                    $live = $this->shifts->resolveForAttendance($attendance);
                    if (abs($live - (float) $attendance->getAttributes()['planned_hours']) < 0.001) {
                        continue;
                    }
                    $this->calculator->recalculate(
                        $attendance->fresh(['segments', 'user']) ?? $attendance,
                        $this->attendanceService,
                    );
                }
            });
    }
}
