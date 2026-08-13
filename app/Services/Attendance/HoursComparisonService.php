<?php

namespace App\Services\Attendance;

use App\Models\Attendance;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Services\Employees\RestDayService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Planned vs actual hours for dashboards (T-2.13).
 *
 * Planned: SCHEDULED+COMPLETED shift_assignments; if none that day →
 * sum of profile default shift windows (FULL_TIME and/or ONLINE_HALF).
 * Date rest days + approved vacation days: planned = 0 unless a shift is assigned.
 */
class HoursComparisonService
{
    public function __construct(
        private readonly ShiftConfig $shifts,
        private readonly RestDayService $restDays,
    ) {}

    /**
     * @return array{
     *     ym: string,
     *     days: list<array{
     *         date: string,
     *         planned_hours: float,
     *         actual_hours: float,
     *         pct: ?float,
     *         from_profile: bool,
     *         is_rest_day: bool,
     *         is_vacation: bool,
     *         planned_intervals: list<array{label: string, start: ?string, end: ?string, hours: float, source: string}>,
     *         actual_intervals: list<array{label: string, start: ?string, end: ?string, hours: float, open: bool}>
     *     }>,
     *     totals: array{planned: float, actual: float, pct: ?float}
     * }
     */
    public function monthForUser(User $user, ?string $ym = null): array
    {
        $month = $this->parseMonth($ym);
        $from = $month->copy()->startOfMonth();
        $to = $month->copy()->endOfMonth();

        $assignments = ShiftAssignment::query()
            ->where('user_id', $user->id)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->whereIn('status', [
                ShiftAssignment::STATUS_SCHEDULED,
                ShiftAssignment::STATUS_COMPLETED,
            ])
            ->orderBy('date')
            ->orderBy('expected_start_time')
            ->get()
            ->groupBy(fn (ShiftAssignment $row) => $row->date->toDateString());

        $attendances = Attendance::query()
            ->where('user_id', $user->id)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->with('photos')
            ->orderBy('date')
            ->orderBy('clock_in')
            ->get()
            ->groupBy(fn (Attendance $row) => $row->date->toDateString());

        $vacationDates = array_fill_keys(
            $this->restDays->approvedVacationDates($user, $from->toDateString(), $to->toDateString()),
            true,
        );

        $days = [];
        $totalPlanned = 0.0;
        $totalActual = 0.0;

        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            $date = $day->toDateString();
            $dayAssignments = $assignments->get($date, collect());
            $dayAttendances = $attendances->get($date, collect());
            $isRestDay = $this->restDays->isRestDate($user, $date);
            $isVacation = isset($vacationDates[$date]);
            $isOff = $isRestDay || $isVacation;

            $planned = $this->plannedForDay($user, $dayAssignments, $isOff);
            $actual = $this->actualForDay($dayAttendances);

            $totalPlanned += $planned['hours'];
            $totalActual += $actual['hours'];

            $days[] = [
                'date' => $date,
                'planned_hours' => $planned['hours'],
                'actual_hours' => $actual['hours'],
                'pct' => $this->pct($actual['hours'], $planned['hours']),
                'from_profile' => $planned['from_profile'],
                'is_rest_day' => $isRestDay,
                'is_vacation' => $isVacation,
                'planned_intervals' => $planned['intervals'],
                'actual_intervals' => $actual['intervals'],
            ];
        }

        $totalPlanned = round($totalPlanned, 2);
        $totalActual = round($totalActual, 2);

        return [
            'ym' => $month->format('Y-m'),
            'days' => $days,
            'totals' => [
                'planned' => $totalPlanned,
                'actual' => $totalActual,
                'pct' => $this->pct($totalActual, $totalPlanned),
            ],
            'shift_breakdown' => $this->monthlyDefaultShiftHours($user, (int) $month->month, (int) $month->year),
        ];
    }

    /**
     * Required vs worked vs absent hours per default base shift for a month (T-7.3).
     * Required = workdays (not rest, not vacation) × planned hours for each selected default.
     *
     * @return array{
     *     work_days: int,
     *     shifts: array<string, array{label: string, required: float, actual: float, absent: float}>
     * }
     */
    public function monthlyDefaultShiftHours(User $user, int $month, int $year): array
    {
        $from = Carbon::create($year, $month, 1)->startOfDay();
        $to = $from->copy()->endOfMonth();
        $defaults = $user->defaultShiftTypes();
        $vacationDates = array_fill_keys(
            $this->restDays->approvedVacationDates($user, $from->toDateString(), $to->toDateString()),
            true,
        );

        $workDays = 0;
        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            $date = $day->toDateString();
            if ($this->restDays->isRestDate($user, $date) || isset($vacationDates[$date])) {
                continue;
            }
            $workDays++;
        }

        $actualByType = Attendance::query()
            ->where('user_id', $user->id)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->whereNotNull('clock_out')
            ->whereIn('shift_type', User::DEFAULT_SHIFT_OPTIONS)
            ->selectRaw('shift_type, COALESCE(SUM(actual_hours), 0) as hours')
            ->groupBy('shift_type')
            ->pluck('hours', 'shift_type');

        $shifts = [];
        foreach (User::DEFAULT_SHIFT_OPTIONS as $type) {
            $perDay = (float) ($this->shifts->plannedHours($type));
            $required = in_array($type, $defaults, true)
                ? round($workDays * $perDay, 2)
                : 0.0;
            $actual = round((float) ($actualByType[$type] ?? 0), 2);
            $shifts[$type] = [
                'label' => __('clock.shifts.'.$type),
                'required' => $required,
                'actual' => $actual,
                'absent' => round(max(0, $required - $actual), 2),
            ];
        }

        return [
            'work_days' => $workDays,
            'shifts' => $shifts,
        ];
    }

    /**
     * @return Collection<int, array{
     *     user: User,
     *     planned: float,
     *     actual: float,
     *     pct: ?float
     * }>
     */
    public function teamMonthSummary(User $actor, ?string $ym = null): Collection
    {
        return $this->visibleEmployees($actor)->map(function (User $employee) use ($ym) {
            $month = $this->monthForUser($employee, $ym);

            return [
                'user' => $employee,
                'planned' => $month['totals']['planned'],
                'actual' => $month['totals']['actual'],
                'pct' => $month['totals']['pct'],
            ];
        })->values();
    }

    /**
     * @return Collection<int, array{
     *     user: User,
     *     status: string,
     *     planned: float,
     *     actual: float,
     *     pct: ?float,
     *     is_clocked_in: bool
     * }>
     */
    public function teamTodayStatus(User $actor, ?CarbonInterface $at = null): Collection
    {
        $at = Carbon::parse($at ?? now());
        $date = $at->toDateString();
        $employees = $this->visibleEmployees($actor);
        $ids = $employees->pluck('id');

        $assignments = ShiftAssignment::query()
            ->whereIn('user_id', $ids)
            ->whereDate('date', $date)
            ->whereIn('status', [
                ShiftAssignment::STATUS_SCHEDULED,
                ShiftAssignment::STATUS_COMPLETED,
            ])
            ->orderBy('expected_start_time')
            ->get()
            ->groupBy('user_id');

        $attendances = Attendance::query()
            ->whereIn('user_id', $ids)
            ->whereDate('date', $date)
            ->with('photos')
            ->orderBy('clock_in')
            ->get()
            ->groupBy('user_id');

        return $employees->map(function (User $employee) use ($assignments, $attendances, $date) {
            $dayAssignments = $assignments->get($employee->id, collect());
            $dayAttendances = $attendances->get($employee->id, collect());
            $isRestDay = $this->restDays->isRestDate($employee, $date);
            $isVacation = in_array($date, $this->restDays->approvedVacationDates($employee, $date, $date), true);
            $isOff = $isRestDay || $isVacation;
            $planned = $this->plannedForDay($employee, $dayAssignments, $isOff);
            $actual = $this->actualForDay($dayAttendances);
            $isIn = $dayAttendances->contains(fn (Attendance $row) => $row->clock_out === null);
            $photoCount = $dayAttendances->sum(fn (Attendance $row) => $row->photos->count());
            $photoModels = $dayAttendances->flatMap(fn (Attendance $row) => $row->photos)->values();

            $status = 'NONE';
            if ($isIn) {
                $status = 'IN';
            } elseif ($dayAttendances->isNotEmpty()) {
                $status = 'OUT';
            } elseif ($isVacation) {
                $status = 'VACATION';
            } elseif ($isRestDay) {
                $status = 'REST';
            }

            return [
                'user' => $employee,
                'status' => $status,
                'planned' => $planned['hours'],
                'actual' => $actual['hours'],
                'pct' => $this->pct($actual['hours'], $planned['hours']),
                'is_clocked_in' => $isIn,
                'is_rest_day' => $isRestDay,
                'is_vacation' => $isVacation,
                'photo_count' => (int) $photoCount,
                'photos' => $photoModels,
                'attendance' => $dayAttendances->first(fn (Attendance $row) => $row->clock_out === null)
                    ?? $dayAttendances->sortByDesc('id')->first(),
            ];
        })->values();
    }

    public function actorMayView(User $actor, User $employee): bool
    {
        if ((int) $actor->id === (int) $employee->id) {
            return true;
        }

        if ($actor->isManager()) {
            return $employee->isEmployee() || $employee->isTeamLeader();
        }

        if ($actor->isTeamLeader()) {
            return $employee->isEmployee()
                && (int) $employee->team_leader_id === (int) $actor->id;
        }

        return false;
    }

    /** @return Collection<int, User> */
    public function visibleEmployees(User $actor): Collection
    {
        if ($actor->isManager()) {
            return User::query()
                ->whereIn('role', [User::ROLE_EMPLOYEE, User::ROLE_TEAM_LEADER])
                ->where('status', User::STATUS_ACTIVE)
                ->orderBy('name_en')
                ->get();
        }

        if ($actor->isTeamLeader()) {
            return User::query()
                ->where('role', User::ROLE_EMPLOYEE)
                ->where('status', User::STATUS_ACTIVE)
                ->where('team_leader_id', $actor->id)
                ->orderBy('name_en')
                ->get();
        }

        return collect();
    }

    public function profilePlannedHours(User $user): float
    {
        $hours = 0.0;
        foreach ($user->defaultShiftTypes() as $type) {
            $hours += $this->shifts->plannedHours($type);
        }

        return round($hours, 2);
    }

    /**
     * @return list<array{label: string, start: ?string, end: ?string, hours: float, source: string}>
     */
    public function profilePlannedIntervals(User $user): array
    {
        $intervals = [];
        foreach ($user->defaultShiftProfiles() as $profile) {
            $h = $this->shifts->plannedHours($profile['type']);
            $intervals[] = [
                'label' => __('clock.shifts.'.$profile['type']),
                'start' => $profile['start'],
                'end' => $profile['end'],
                'hours' => $h,
                'source' => 'profile',
            ];
        }

        return $intervals;
    }

    /**
     * Calendar markers: shift type + hours for planned and actual + rest days.
     *
     * @param  list<array{date: string, from_profile: bool, is_rest_day?: bool, planned_intervals: list, actual_intervals: list}>  $days
     * @return array<string, list<array{kind: string, label: string}>>
     */
    public function calendarMarkersFromDays(array $days): array
    {
        $markers = [];

        foreach ($days as $day) {
            $date = $day['date'];

            if (! empty($day['is_rest_day'])) {
                $markers[$date][] = [
                    'kind' => 'rest_day',
                    'label' => __('dashboard.hours.rest_day'),
                ];
            }

            if (! empty($day['is_vacation'])) {
                $markers[$date][] = [
                    'kind' => 'vacation',
                    'label' => __('dashboard.hours.vacation'),
                ];
            }

            foreach ($day['planned_intervals'] as $interval) {
                $source = $interval['source'] ?? '';
                if ($source === 'rest_day') {
                    continue;
                }
                $kind = $source === 'profile' ? 'planned_default' : 'planned';
                $markers[$date][] = [
                    'kind' => $kind,
                    'label' => $interval['label'].' '.$this->formatHours($interval['hours']),
                ];
            }

            foreach ($day['actual_intervals'] as $interval) {
                $suffix = $interval['open']
                    ? __('dashboard.hours.open')
                    : $this->formatHours($interval['hours']);
                $markers[$date][] = [
                    'kind' => 'actual',
                    'label' => $interval['label'].' '.$suffix,
                ];
            }
        }

        return $markers;
    }

    /**
     * @param  Collection<int, ShiftAssignment>  $assignments
     * @return array{hours: float, from_profile: bool, intervals: list<array{label: string, start: ?string, end: ?string, hours: float, source: string}>}
     */
    private function plannedForDay(User $user, Collection $assignments, bool $isRestDay = false): array
    {
        if ($assignments->isNotEmpty()) {
            $intervals = [];
            $hours = 0.0;

            foreach ($assignments as $assignment) {
                $h = round((float) $assignment->expected_hours, 2);
                $hours += $h;
                $intervals[] = [
                    'label' => __('shifts.types.'.$assignment->shift_type),
                    'start' => $assignment->expected_start_time,
                    'end' => $assignment->expected_end_time,
                    'hours' => $h,
                    'source' => 'assignment',
                ];
            }

            return [
                'hours' => round($hours, 2),
                'from_profile' => false,
                'intervals' => $intervals,
            ];
        }

        if ($isRestDay) {
            return [
                'hours' => 0.0,
                'from_profile' => false,
                'intervals' => [[
                    'label' => __('dashboard.hours.rest_day'),
                    'start' => null,
                    'end' => null,
                    'hours' => 0.0,
                    'source' => 'rest_day',
                ]],
            ];
        }

        $hours = $this->profilePlannedHours($user);
        $intervals = $this->profilePlannedIntervals($user);

        return [
            'hours' => $hours,
            'from_profile' => true,
            'intervals' => $intervals !== [] ? $intervals : [[
                'label' => __('dashboard.hours.default_window'),
                'start' => null,
                'end' => null,
                'hours' => $hours,
                'source' => 'profile',
            ]],
        ];
    }

    /**
     * @param  Collection<int, Attendance>  $attendances
     * @return array{hours: float, intervals: list<array{label: string, start: ?string, end: ?string, hours: float, open: bool, is_friday_work: bool, is_day_off_work: bool, is_official_holiday_work: bool}>}
     */
    private function actualForDay(Collection $attendances): array
    {
        $intervals = [];
        $hours = 0.0;

        foreach ($attendances as $row) {
            $open = $row->clock_out === null;
            $h = $open ? 0.0 : round((float) $row->actual_hours, 2);
            $hours += $h;
            $photos = $row->relationLoaded('photos') ? $row->photos : collect();
            $intervals[] = [
                'label' => __('clock.shifts.'.$row->shift_type),
                'start' => $row->clock_in?->format('H:i'),
                'end' => $row->clock_out?->format('H:i'),
                'hours' => $h,
                'open' => $open,
                'is_friday_work' => (bool) $row->is_friday_work,
                'is_day_off_work' => (bool) $row->is_day_off_work,
                'is_official_holiday_work' => (bool) $row->is_official_holiday_work,
                'photos' => $photos,
                'attendance' => $row,
            ];
        }

        return [
            'hours' => round($hours, 2),
            'intervals' => $intervals,
        ];
    }

    private function pct(float $actual, float $planned): ?float
    {
        if ($planned <= 0) {
            return null;
        }

        return round(min(100, ($actual / $planned) * 100), 2);
    }

    private function formatHours(float $hours): string
    {
        return number_format($hours, 2).'h';
    }

    private function parseMonth(?string $ym): Carbon
    {
        if ($ym !== null && preg_match('/^\d{4}-\d{2}$/', $ym) === 1) {
            return Carbon::createFromFormat('Y-m', $ym)->startOfMonth();
        }

        return now()->startOfMonth();
    }
}
