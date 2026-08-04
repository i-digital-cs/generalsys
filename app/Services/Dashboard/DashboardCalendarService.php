<?php

namespace App\Services\Dashboard;

use App\Models\OfficialHoliday;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Services\Attendance\HoursComparisonService;
use Illuminate\Support\Collection;

/**
 * Calendar markers for role dashboards (shifts, hours, holidays).
 */
class DashboardCalendarService
{
    public function __construct(
        private readonly MonthCalendarBuilder $calendar,
        private readonly HoursComparisonService $hours,
    ) {}

    /**
     * @return array{
     *     calendar: array,
     *     assignedShifts: Collection<int, ShiftAssignment>,
     *     hoursMonth: array
     * }
     */
    public function forEmployee(User $user, ?string $cal = null): array
    {
        $month = $this->calendar->parseMonthParam($cal);
        $ym = $month->format('Y-m');
        $from = $month->copy()->startOfMonth()->toDateString();
        $to = $month->copy()->endOfMonth()->toDateString();

        $hoursMonth = $this->hours->monthForUser($user, $ym);

        $assignedShifts = ShiftAssignment::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [
                ShiftAssignment::STATUS_SCHEDULED,
                ShiftAssignment::STATUS_COMPLETED,
            ])
            ->whereDate('date', '>=', now()->subDays(7)->toDateString())
            ->orderBy('date')
            ->orderBy('expected_start_time')
            ->limit(50)
            ->get();

        $markers = $this->holidayMarkers($from, $to);
        $this->mergeMarkers($markers, $this->hours->calendarMarkersFromDays($hoursMonth['days']));

        return [
            'calendar' => $this->calendar->build($month, $markers),
            'assignedShifts' => $assignedShifts,
            'hoursMonth' => $hoursMonth,
        ];
    }

    /**
     * @return array{
     *     calendar: array,
     *     teamShifts: Collection<int, ShiftAssignment>,
     *     hoursMonth: ?array,
     *     teamSummary: Collection,
     *     todayStatus: Collection,
     *     focusUser: ?User,
     *     teamMembers: Collection<int, User>
     * }
     */
    public function forTeamLeader(User $leader, ?string $cal = null, ?int $focusUserId = null): array
    {
        return $this->forActorTeam($leader, $cal, $focusUserId);
    }

    /**
     * @return array{
     *     calendar: array,
     *     teamShifts: Collection<int, ShiftAssignment>,
     *     hoursMonth: ?array,
     *     teamSummary: Collection,
     *     todayStatus: Collection,
     *     focusUser: ?User,
     *     teamMembers: Collection<int, User>
     * }
     */
    public function forManager(User $manager, ?string $cal = null, ?int $focusUserId = null): array
    {
        return $this->forActorTeam($manager, $cal, $focusUserId);
    }

    /**
     * @return array{calendar: array, teamShifts: Collection<int, ShiftAssignment>}
     */
    public function holidaysOnly(?string $cal = null): array
    {
        $month = $this->calendar->parseMonthParam($cal);
        $from = $month->copy()->startOfMonth()->toDateString();
        $to = $month->copy()->endOfMonth()->toDateString();

        return [
            'calendar' => $this->calendar->build($month, $this->holidayMarkers($from, $to)),
            'teamShifts' => collect(),
        ];
    }

    /**
     * @return array{
     *     calendar: array,
     *     teamShifts: Collection<int, ShiftAssignment>,
     *     hoursMonth: ?array,
     *     teamSummary: Collection,
     *     todayStatus: Collection,
     *     focusUser: ?User,
     *     teamMembers: Collection<int, User>
     * }
     */
    /**
     * @return array{
     *     calendar: array,
     *     teamShifts: Collection<int, ShiftAssignment>,
     *     hoursMonth: ?array,
     *     teamSummary: Collection,
     *     todayStatus: Collection,
     *     focusUser: ?User,
     *     teamMembers: Collection<int, User>,
     *     focusOptions: Collection<int, User>
     * }
     */
    private function forActorTeam(User $actor, ?string $cal, ?int $focusUserId): array
    {
        $month = $this->calendar->parseMonthParam($cal);
        $ym = $month->format('Y-m');
        $from = $month->copy()->startOfMonth()->toDateString();
        $to = $month->copy()->endOfMonth()->toDateString();

        $teamMembers = $this->hours->visibleEmployees($actor);
        $teamSummary = $this->hours->teamMonthSummary($actor, $ym);
        $todayStatus = $this->hours->teamTodayStatus($actor);

        $focusOptions = collect([$actor])->concat($teamMembers)->unique('id')->values();

        $focusUser = null;
        if ($focusUserId !== null) {
            if ((int) $focusUserId === (int) $actor->id) {
                $focusUser = $actor;
            } else {
                $focusUser = $teamMembers->firstWhere('id', $focusUserId);
            }
        }
        // Default: own calendar (Me) for TL/Admin.
        $focusUser ??= $actor;

        $hoursMonth = $this->hours->monthForUser($focusUser, $ym);
        $markers = $this->holidayMarkers($from, $to);
        $this->mergeMarkers($markers, $this->hours->calendarMarkersFromDays($hoursMonth['days']));

        $teamShifts = ShiftAssignment::query()
            ->with('user')
            ->whereIn('user_id', $teamMembers->pluck('id'))
            ->whereBetween('date', [$from, $to])
            ->whereIn('status', [
                ShiftAssignment::STATUS_SCHEDULED,
                ShiftAssignment::STATUS_COMPLETED,
            ])
            ->orderBy('date')
            ->orderBy('expected_start_time')
            ->orderBy('user_id')
            ->get();

        return [
            'calendar' => $this->calendar->build($month, $markers),
            'teamShifts' => $teamShifts,
            'hoursMonth' => $hoursMonth,
            'teamSummary' => $teamSummary,
            'todayStatus' => $todayStatus,
            'focusUser' => $focusUser,
            'teamMembers' => $teamMembers,
            'focusOptions' => $focusOptions,
        ];
    }

    /**
     * @param  array<string, list<array{kind: string, label: string}>>  $into
     * @param  array<string, list<array{kind: string, label: string}>>  $from
     */
    private function mergeMarkers(array &$into, array $from): void
    {
        foreach ($from as $date => $items) {
            foreach ($items as $item) {
                $into[$date][] = $item;
            }
        }
    }

    /**
     * @return array<string, list<array{kind: string, label: string}>>
     */
    private function holidayMarkers(string $from, string $to): array
    {
        $markers = [];

        $holidays = OfficialHoliday::query()
            ->whereBetween('date', [$from, $to])
            ->orderBy('date')
            ->get();

        foreach ($holidays as $holiday) {
            $date = $holiday->date->toDateString();
            $markers[$date][] = [
                'kind' => 'holiday',
                'label' => app()->getLocale() === 'ar' ? $holiday->name_ar : $holiday->name_en,
            ];
        }

        return $markers;
    }
}
