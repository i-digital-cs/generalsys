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
 * profile work_start–work_end duration (overnight-safe); if times missing →
 * ShiftConfig hours for shift_type_default.
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
            ];
        })->values();
    }

    public function actorMayView(User $actor, User $employee): bool
    {
        if ((int) $actor->id === (int) $employee->id) {
            return true;
        }

        if (! $employee->isEmployee()) {
            return false;
        }

        if ($actor->isManager()) {
            return true;
        }

        if ($actor->isTeamLeader()) {
            return (int) $employee->team_leader_id === (int) $actor->id;
        }

        return false;
    }

    /** @return Collection<int, User> */
    public function visibleEmployees(User $actor): Collection
    {
        $query = User::query()
            ->where('role', User::ROLE_EMPLOYEE)
            ->where('status', User::STATUS_ACTIVE)
            ->orderBy('name_en');

        if ($actor->isTeamLeader()) {
            $query->where('team_leader_id', $actor->id);
        } elseif (! $actor->isManager()) {
            return collect();
        }

        return $query->get();
    }

    public function profilePlannedHours(User $user): float
    {
        $start = $user->work_start_time;
        $end = $user->work_end_time;

        if ($start !== null && $end !== null && $start !== '' && $end !== '') {
            $from = Carbon::createFromFormat('H:i', $start);
            $to = Carbon::createFromFormat('H:i', $end);

            if ($to->lte($from)) {
                $to->addDay();
            }

            return round($from->diffInMinutes($to) / 60, 2);
        }

        $defaultType = $user->shift_type_default ?: Attendance::SHIFT_FULL_TIME;

        return $this->shifts->plannedHours($defaultType);
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
        $start = $user->work_start_time;
        $end = $user->work_end_time;

        return [
            'hours' => $hours,
            'from_profile' => true,
            'intervals' => [[
                'label' => __('dashboard.hours.default_window'),
                'start' => $start,
                'end' => $end,
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
            $intervals[] = [
                'label' => __('clock.shifts.'.$row->shift_type),
                'start' => $row->clock_in?->format('H:i'),
                'end' => $row->clock_out?->format('H:i'),
                'hours' => $h,
                'open' => $open,
                'is_friday_work' => (bool) $row->is_friday_work,
                'is_day_off_work' => (bool) $row->is_day_off_work,
                'is_official_holiday_work' => (bool) $row->is_official_holiday_work,
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
