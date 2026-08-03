<?php

namespace App\Services\Employee;

use App\Models\Attendance;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Services\Attendance\AttendanceService;
use App\Services\Dashboard\DashboardCalendarService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Employee home dashboard snapshot (T-2.4).
 */
class EmployeeDashboardService
{
    public function __construct(
        private readonly AttendanceService $attendance,
        private readonly DashboardCalendarService $calendar,
    ) {}

    /**
     * @return array{
     *     open: ?Attendance,
     *     assignment: ?ShiftAssignment,
     *     preferredShift: string,
     *     todaySessions: Collection<int, Attendance>,
     *     hoursToday: float,
     *     isClockedIn: bool,
     *     assignedShifts: Collection<int, ShiftAssignment>,
     *     calendar: array
     * }
     */
    public function snapshot(User $user, ?Carbon $at = null, ?string $cal = null): array
    {
        $at = $at ?? now();
        $date = $at->toDateString();

        $open = $this->attendance->openAttendance($user);

        $todaySessions = Attendance::query()
            ->where('user_id', $user->id)
            ->whereDate('date', $date)
            ->orderBy('clock_in')
            ->get();

        $hoursToday = round((float) $todaySessions->sum(fn (Attendance $row) => (float) $row->actual_hours), 2);
        $calData = $this->calendar->forEmployee($user, $cal);

        return [
            'open' => $open,
            'assignment' => $this->attendance->todaysPrimaryAssignment($user, $at),
            'preferredShift' => $this->attendance->preferredShiftType($user, $at),
            'todaySessions' => $todaySessions,
            'hoursToday' => $hoursToday,
            'isClockedIn' => $open !== null,
            'assignedShifts' => $calData['assignedShifts'],
            'calendar' => $calData['calendar'],
        ];
    }
}
