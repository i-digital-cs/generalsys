<?php

namespace App\Services\Employees;

use App\Models\Attendance;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Services\Attendance\AttendanceService;
use App\Services\Attendance\ClockCtaColorService;
use App\Services\Dashboard\DashboardCalendarService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Employee home dashboard snapshot (T-2.4).
 * Namespace: Employees (Phase 6 — consolidated from App\Services\Employee).
 */
class EmployeeDashboardService
{
    public function __construct(
        private readonly AttendanceService $attendance,
        private readonly DashboardCalendarService $calendar,
        private readonly ClockCtaColorService $ctaColors,
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
     *     calendar: array,
     *     hoursMonth: array,
     *     teamLeaders: Collection<int, User>,
     *     cta: array{color: string, expectedAt: ?Carbon, clockInStarts: array<string, ?string>},
     *     ctaWindows: array
     * }
     */
    public function snapshot(User $user, ?Carbon $at = null, ?string $cal = null): array
    {
        $at = $at ?? now();
        $date = $at->toDateString();
        $user->loadMissing(['teamLeaders.profile']);

        $open = $this->attendance->openAttendance($user);

        $todaySessions = Attendance::query()
            ->where('user_id', $user->id)
            ->whereDate('date', $date)
            ->with(['photos', 'segments', 'gaps'])
            ->orderBy('clock_in')
            ->get();

        $hoursToday = round((float) $todaySessions->sum(fn (Attendance $row) => (float) $row->actual_hours), 2);
        $calData = $this->calendar->forEmployee($user, $cal);
        $preferredShift = $this->attendance->preferredShiftType($user, $at);

        return [
            'open' => $open,
            'assignment' => $this->attendance->todaysPrimaryAssignment($user, $at),
            'preferredShift' => $preferredShift,
            'todaySessions' => $todaySessions,
            'hoursToday' => $hoursToday,
            'isClockedIn' => $open !== null,
            'assignedShifts' => $calData['assignedShifts'],
            'calendar' => $calData['calendar'],
            'hoursMonth' => $calData['hoursMonth'],
            'teamLeaders' => $user->teamLeaders,
            'cta' => $this->ctaColors->buttonState($user, $open, $preferredShift, [], $at),
            'ctaWindows' => $this->ctaColors->windows(),
        ];
    }
}
