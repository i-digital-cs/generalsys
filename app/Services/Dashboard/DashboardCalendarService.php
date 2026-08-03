<?php

namespace App\Services\Dashboard;

use App\Models\OfficialHoliday;
use App\Models\ShiftAssignment;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Calendar markers for role dashboards (shifts + official holidays).
 */
class DashboardCalendarService
{
    public function __construct(private readonly MonthCalendarBuilder $calendar) {}

    /**
     * @return array{calendar: array, assignedShifts: Collection<int, ShiftAssignment>}
     */
    public function forEmployee(User $user, ?string $cal = null): array
    {
        $month = $this->calendar->parseMonthParam($cal);
        $from = $month->copy()->startOfMonth()->toDateString();
        $to = $month->copy()->endOfMonth()->toDateString();

        $monthShifts = ShiftAssignment::query()
            ->where('user_id', $user->id)
            ->whereBetween('date', [$from, $to])
            ->whereIn('status', [
                ShiftAssignment::STATUS_SCHEDULED,
                ShiftAssignment::STATUS_COMPLETED,
            ])
            ->orderBy('date')
            ->orderBy('expected_start_time')
            ->get();

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

        foreach ($monthShifts as $shift) {
            $date = $shift->date->toDateString();
            $markers[$date][] = [
                'kind' => 'shift',
                'label' => __('shifts.types.'.$shift->shift_type),
            ];
        }

        return [
            'calendar' => $this->calendar->build($month, $markers),
            'assignedShifts' => $assignedShifts,
        ];
    }

    /**
     * @return array{calendar: array, teamShifts: Collection<int, ShiftAssignment>}
     */
    public function forTeamLeader(User $leader, ?string $cal = null): array
    {
        $month = $this->calendar->parseMonthParam($cal);
        $from = $month->copy()->startOfMonth()->toDateString();
        $to = $month->copy()->endOfMonth()->toDateString();

        $markers = $this->holidayMarkers($from, $to);

        $teamIds = User::query()
            ->where('team_leader_id', $leader->id)
            ->where('role', User::ROLE_EMPLOYEE)
            ->pluck('id');

        $teamShifts = ShiftAssignment::query()
            ->with('user')
            ->whereIn('user_id', $teamIds)
            ->whereBetween('date', [$from, $to])
            ->whereIn('status', [
                ShiftAssignment::STATUS_SCHEDULED,
                ShiftAssignment::STATUS_COMPLETED,
            ])
            ->orderBy('date')
            ->orderBy('expected_start_time')
            ->orderBy('user_id')
            ->get();

        foreach ($teamShifts as $shift) {
            $date = $shift->date->toDateString();
            $name = app()->getLocale() === 'ar' ? $shift->user?->name_ar : $shift->user?->name_en;
            $markers[$date][] = [
                'kind' => 'shift',
                'label' => trim(($name ?? '').' · '.__('shifts.types.'.$shift->shift_type)),
            ];
        }

        return [
            'calendar' => $this->calendar->build($month, $markers),
            'teamShifts' => $teamShifts,
        ];
    }

    /**
     * @return array{calendar: array, teamShifts: Collection<int, ShiftAssignment>}
     */
    public function forManager(?string $cal = null): array
    {
        $month = $this->calendar->parseMonthParam($cal);
        $from = $month->copy()->startOfMonth()->toDateString();
        $to = $month->copy()->endOfMonth()->toDateString();

        $markers = $this->holidayMarkers($from, $to);

        $teamShifts = ShiftAssignment::query()
            ->with('user')
            ->whereBetween('date', [$from, $to])
            ->whereIn('status', [
                ShiftAssignment::STATUS_SCHEDULED,
                ShiftAssignment::STATUS_COMPLETED,
            ])
            ->orderBy('date')
            ->orderBy('expected_start_time')
            ->orderBy('user_id')
            ->limit(100)
            ->get();

        foreach ($teamShifts as $shift) {
            $date = $shift->date->toDateString();
            $name = app()->getLocale() === 'ar' ? $shift->user?->name_ar : $shift->user?->name_en;
            $markers[$date][] = [
                'kind' => 'shift',
                'label' => trim(($name ?? '').' · '.__('shifts.types.'.$shift->shift_type)),
            ];
        }

        return [
            'calendar' => $this->calendar->build($month, $markers),
            'teamShifts' => $teamShifts,
        ];
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
