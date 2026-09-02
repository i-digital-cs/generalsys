<?php

namespace App\Services\Attendance;

use App\Models\Attendance;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Services\Employees\RestDayService;
use App\Services\Employees\TeamMembershipService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Planned vs actual hours for dashboards (T-2.13).
 *
 * Planned: SCHEDULED+COMPLETED shift_assignments; if none that day →
 * sum of profile default shift windows (FULL_TIME and/or ONLINE_HALF).
 * Dual defaults stay on the day when a TL assigns only one type (overlay).
 * Date rest days + approved vacation days: planned = 0 unless a shift is assigned.
 */
class HoursComparisonService
{
    public function __construct(
        private readonly ShiftConfig $shifts,
        private readonly RestDayService $restDays,
        private readonly TeamMembershipService $membership,
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
            $shiftLines = $this->shiftLinesForDay($planned['intervals'], $actual['intervals']);

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
                'shift_lines' => $shiftLines,
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
            'shift_breakdown' => $this->aggregateDefaultShiftBreakdown($days, $user),
        ];
    }

    /**
     * Required vs worked vs absent hours per default base shift for a month (T-7.3).
     * Sums exact per-day planned/actual lines (a single assignment overlays that
     * type; dual defaults stay on the day).
     *
     * @return array{
     *     work_days: int,
     *     shifts: array<string, array{label: string, required: float, actual: float, absent: float}>
     * }
     */
    public function monthlyDefaultShiftHours(User $user, int $month, int $year): array
    {
        $ym = sprintf('%04d-%02d', $year, $month);
        $monthData = $this->monthForUser($user, $ym);

        return $monthData['shift_breakdown'];
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
            ->with(['photos', 'segments', 'gaps'])
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
                'attendances' => $dayAttendances->values(),
            ];
        })->values();
    }

    public function actorMayView(User $actor, User $employee): bool
    {
        if ((int) $actor->id === (int) $employee->id) {
            return true;
        }

        if ($actor->canManageOperations()) {
            return $employee->isFieldStaff() || $employee->isTeamLeader();
        }

        if ($actor->isTeamLeader()) {
            return $employee->isFieldStaff()
                && $this->membership->leads($actor, $employee);
        }

        return false;
    }

    /** @return Collection<int, User> */
    public function visibleEmployees(User $actor): Collection
    {
        return $this->membership->visibleEmployees($actor);
    }

    public function profilePlannedHours(User $user): float
    {
        $hours = 0.0;
        foreach ($user->defaultShiftProfiles() as $profile) {
            $hours += $this->plannedHoursForProfile($profile);
        }

        return round($hours, 2);
    }

    /**
     * @return list<array{label: string, start: ?string, end: ?string, hours: float, source: string, shift_type: ?string}>
     */
    public function profilePlannedIntervals(User $user): array
    {
        $intervals = [];
        foreach ($user->defaultShiftProfiles() as $profile) {
            $h = $this->plannedHoursForProfile($profile);
            $intervals[] = [
                'label' => __('clock.shifts.'.$profile['type']),
                'start' => $profile['start'],
                'end' => $profile['end'],
                'hours' => $h,
                'source' => 'profile',
                'shift_type' => $profile['type'],
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
    public function plannedForDay(User $user, Collection $assignments, bool $isRestDay = false): array
    {
        if ($isRestDay) {
            return $assignments->isEmpty()
                ? $this->restDayPlan()
                : $this->assignmentPlan($assignments);
        }

        if ($assignments->isEmpty()) {
            return $this->profilePlan($user);
        }

        if (count($user->defaultShiftTypes()) >= 2) {
            return $this->overlayAssignmentsOnDefaults($user, $assignments);
        }

        return $this->assignmentPlan($assignments);
    }

    /**
     * @param  Collection<int, ShiftAssignment>  $assignments
     * @return array{hours: float, from_profile: bool, intervals: list<array{label: string, start: ?string, end: ?string, hours: float, source: string, shift_type: ?string}>}
     */
    private function assignmentPlan(Collection $assignments): array
    {
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
                'shift_type' => $assignment->shift_type,
            ];
        }

        return [
            'hours' => round($hours, 2),
            'from_profile' => false,
            'intervals' => $intervals,
        ];
    }

    /**
     * Dual defaults stay on the day; matching assignments replace that type's window.
     *
     * @param  Collection<int, ShiftAssignment>  $assignments
     * @return array{hours: float, from_profile: bool, intervals: list<array{label: string, start: ?string, end: ?string, hours: float, source: string, shift_type: ?string}>}
     */
    private function overlayAssignmentsOnDefaults(User $user, Collection $assignments): array
    {
        $byType = [];
        foreach ($this->profilePlannedIntervals($user) as $interval) {
            $type = $interval['shift_type'] ?? null;
            if (is_string($type) && $type !== '') {
                $byType[$type] = $interval;
            }
        }

        foreach ($this->assignmentPlan($assignments)['intervals'] as $interval) {
            $type = $interval['shift_type'] ?? null;
            if (is_string($type) && $type !== '') {
                $byType[$type] = $interval;
            }
        }

        $intervals = array_values($byType);
        $hours = 0.0;
        foreach ($intervals as $interval) {
            $hours += (float) $interval['hours'];
        }

        return [
            'hours' => round($hours, 2),
            'from_profile' => false,
            'intervals' => $intervals,
        ];
    }

    /**
     * @return array{hours: float, from_profile: bool, intervals: list<array{label: string, start: ?string, end: ?string, hours: float, source: string, shift_type: ?string}>}
     */
    private function restDayPlan(): array
    {
        return [
            'hours' => 0.0,
            'from_profile' => false,
            'intervals' => [[
                'label' => __('dashboard.hours.rest_day'),
                'start' => null,
                'end' => null,
                'hours' => 0.0,
                'source' => 'rest_day',
                'shift_type' => null,
            ]],
        ];
    }

    /**
     * @return array{hours: float, from_profile: bool, intervals: list<array{label: string, start: ?string, end: ?string, hours: float, source: string, shift_type: ?string}>}
     */
    private function profilePlan(User $user): array
    {
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
                'shift_type' => null,
            ]],
        ];
    }

    /**
     * Exact required / existing / absent hours per shift type for one day.
     *
     * @param  list<array{label: string, hours: float, source?: string, shift_type?: ?string}>  $plannedIntervals
     * @param  list<array{label: string, hours: float, open?: bool, shift_type?: ?string}>  $actualIntervals
     * @return list<array{shift_type: ?string, label: string, required: float, actual: float, absent: float}>
     */
    public function shiftLinesForDay(array $plannedIntervals, array $actualIntervals): array
    {
        $requiredByType = [];
        $labels = [];

        foreach ($plannedIntervals as $interval) {
            if (($interval['source'] ?? '') === 'rest_day') {
                continue;
            }
            $type = $interval['shift_type'] ?? null;
            $key = is_string($type) && $type !== '' ? $type : '_'.$interval['label'];
            $requiredByType[$key] = ($requiredByType[$key] ?? 0) + (float) $interval['hours'];
            $labels[$key] = $interval['label'];
        }

        $actualByType = [];
        foreach ($actualIntervals as $interval) {
            if (! empty($interval['open'])) {
                continue;
            }
            $type = $interval['shift_type'] ?? null;
            $key = is_string($type) && $type !== '' ? $type : '_'.$interval['label'];
            $actualByType[$key] = ($actualByType[$key] ?? 0) + (float) $interval['hours'];
            $labels[$key] = $labels[$key] ?? $interval['label'];
        }

        $keys = array_unique(array_merge(array_keys($requiredByType), array_keys($actualByType)));
        $lines = [];
        foreach ($keys as $key) {
            $required = round((float) ($requiredByType[$key] ?? 0), 2);
            $actual = round((float) ($actualByType[$key] ?? 0), 2);
            $type = str_starts_with((string) $key, '_') ? null : (string) $key;
            $lines[] = [
                'shift_type' => $type,
                'label' => $labels[$key] ?? ($type ? __('clock.shifts.'.$type) : __('general.not_available')),
                'required' => $required,
                'actual' => $actual,
                'absent' => round(max(0, $required - $actual), 2),
            ];
        }

        usort($lines, function (array $a, array $b): int {
            $order = array_flip(User::DEFAULT_SHIFT_OPTIONS);

            return ($order[$a['shift_type'] ?? ''] ?? 99) <=> ($order[$b['shift_type'] ?? ''] ?? 99);
        });

        return $lines;
    }

    /**
     * @param  list<array{is_rest_day?: bool, is_vacation?: bool, shift_lines?: list}>  $days
     * @return array{
     *     work_days: int,
     *     shifts: array<string, array{label: string, required: float, actual: float, absent: float}>
     * }
     */
    private function aggregateDefaultShiftBreakdown(array $days, User $user): array
    {
        $defaults = $user->defaultShiftTypes();
        $workDays = 0;
        $required = [];
        $actual = [];

        foreach (User::DEFAULT_SHIFT_OPTIONS as $type) {
            $required[$type] = 0.0;
            $actual[$type] = 0.0;
        }

        foreach ($days as $day) {
            $isOff = ! empty($day['is_rest_day']) || ! empty($day['is_vacation']);
            $lines = $day['shift_lines'] ?? [];
            if (! $isOff && $lines !== []) {
                $workDays++;
            }

            foreach ($lines as $line) {
                $type = $line['shift_type'] ?? null;
                if (! is_string($type) || ! in_array($type, User::DEFAULT_SHIFT_OPTIONS, true)) {
                    continue;
                }
                $required[$type] += (float) $line['required'];
                $actual[$type] += (float) $line['actual'];
            }
        }

        $shifts = [];
        foreach (User::DEFAULT_SHIFT_OPTIONS as $type) {
            $req = in_array($type, $defaults, true) || $required[$type] > 0
                ? round($required[$type], 2)
                : 0.0;
            // Always show actual for default options when hours were punched,
            // even if type is not in defaults (legacy / assignment).
            $act = round($actual[$type], 2);
            if (! in_array($type, $defaults, true) && $req <= 0 && $act <= 0) {
                $req = 0.0;
            }
            $shifts[$type] = [
                'label' => __('clock.shifts.'.$type),
                'required' => $req,
                'actual' => $act,
                'absent' => round(max(0, $req - $act), 2),
            ];
        }

        return [
            'work_days' => $workDays,
            'shifts' => $shifts,
        ];
    }

    /**
     * @param  array{type: string, start: string, end: string, expected_hours?: float}  $profile
     */
    private function plannedHoursForProfile(array $profile): float
    {
        if (isset($profile['expected_hours'])) {
            return round((float) $profile['expected_hours'], 2);
        }

        $fromWindow = $this->hoursBetween($profile['start'] ?? null, $profile['end'] ?? null);
        if ($fromWindow > 0) {
            return $fromWindow;
        }

        return (float) $this->shifts->plannedHours($profile['type']);
    }

    private function hoursBetween(?string $start, ?string $end): float
    {
        if ($start === null || $start === '' || $end === null || $end === '') {
            return 0.0;
        }

        $from = Carbon::createFromFormat('H:i', substr($start, 0, 5));
        $to = Carbon::createFromFormat('H:i', substr($end, 0, 5));
        if ($from === false || $to === false) {
            return 0.0;
        }

        if ($to->lte($from)) {
            $to->addDay();
        }

        return round($from->floatDiffInHours($to), 2);
    }

    /**
     * @param  Collection<int, Attendance>  $attendances
     * @return array{hours: float, intervals: list<array{label: string, start: ?string, end: ?string, hours: float, open: bool, is_friday_work: bool, is_day_off_work: bool, is_official_holiday_work: bool}>}
     */
    public function actualForDay(Collection $attendances): array
    {
        $intervals = [];
        $hours = 0.0;

        foreach ($attendances as $row) {
            $row->loadMissing(['segments', 'gaps']);
            $open = $row->clock_out === null;
            $credited = $open ? 0.0 : round((float) ($row->credited_hours ?? $row->actual_hours), 2);
            $hours += $credited;
            $photos = $row->relationLoaded('photos') ? $row->photos : collect();
            $baseMeta = [
                'shift_type' => $row->shift_type,
                'is_friday_work' => (bool) $row->is_friday_work,
                'is_day_off_work' => (bool) $row->is_day_off_work,
                'is_official_holiday_work' => (bool) $row->is_official_holiday_work,
                'photos' => $photos,
                'attendance' => $row,
                'late_minutes' => (int) $row->late_minutes,
                'early_departure_minutes' => (int) $row->early_departure_minutes,
                'raw_hours' => round((float) ($row->raw_actual_hours ?? $row->actual_hours), 2),
                'credited_hours' => $credited,
            ];

            if ($row->segments->isNotEmpty()) {
                foreach ($row->segments->sortBy('sequence') as $index => $segment) {
                    $segmentOpen = $segment->clock_out === null;
                    $segmentHours = $segmentOpen ? 0.0 : round($segment->workedMinutes() / 60, 2);
                    $intervals[] = array_merge($baseMeta, [
                        'label' => __('attendance_review.segment_n', ['n' => $index + 1]).' · '.__('clock.shifts.'.$row->shift_type),
                        'start' => $segment->clock_in?->format('H:i'),
                        'end' => $segment->clock_out?->format('H:i'),
                        'hours' => $segmentHours,
                        'open' => $segmentOpen,
                    ]);
                }

                continue;
            }

            $intervals[] = array_merge($baseMeta, [
                'label' => __('clock.shifts.'.$row->shift_type),
                'start' => $row->clock_in?->format('H:i'),
                'end' => $row->clock_out?->format('H:i'),
                'hours' => $credited,
                'open' => $open,
            ]);
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
