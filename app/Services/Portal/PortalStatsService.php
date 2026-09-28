<?php

namespace App\Services\Portal;

use App\Models\Attendance;
use App\Models\User;
use App\Services\Attendance\MissingDailyReportService;
use App\Services\Attendance\ShiftConfig;
use App\Services\Employees\TeamMembershipService;
use App\Support\AppModule;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Company portal /home snapshot (T-9.2). Counts and hours only — never payroll money.
 */
class PortalStatsService
{
    private const TREND_DAYS = 14;

    public function __construct(
        private readonly TeamMembershipService $membership,
        private readonly MissingDailyReportService $missingReports,
        private readonly ShiftConfig $shifts,
    ) {}

    /**
     * @return array{
     *     scope: string,
     *     cards: array<string, int|string>,
     *     trend: list<array{date: string, label: string, value: int}>,
     *     roles: list<array{key: string, label: string, value: int, color: string}>,
     *     modules: list<array{key: string, label: string, value: int}>,
     *     hours: array{planned: float, actual: float}
     * }
     */
    public function snapshot(User $actor, ?CarbonInterface $now = null): array
    {
        $now = Carbon::parse($now ?? now())->timezone(config('app.timezone'));
        $ids = $this->staffScopeIds($actor);
        $today = $now->toDateString();

        return [
            'scope' => $actor->isTeamLeader() && ! $actor->canManageOperations() ? 'team' : 'company',
            'cards' => $this->cards($actor, $ids, $today, $now),
            'trend' => $this->attendanceTrend($ids, $now),
            'roles' => $this->roleSeries($actor, $ids),
            'modules' => $this->moduleSeries($actor, $ids),
            'hours' => $this->monthHours($ids, $now),
        ];
    }

    /**
     * @return list<int>
     */
    public function staffScopeIds(User $actor): array
    {
        if ($actor->isTeamLeader() && ! $actor->canManageOperations()) {
            $ids = $this->membership->visibleEmployeeIds($actor);
            $ids[] = (int) $actor->id;

            return array_values(array_unique($ids));
        }

        return User::query()
            ->whereIn('role', User::operationsVisibleRoles())
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * @param  list<int>  $ids
     * @return array<string, int>
     */
    private function cards(User $actor, array $ids, string $today, Carbon $now): array
    {
        $active = 0;
        if ($ids !== []) {
            $active = (int) User::query()
                ->whereIn('id', $ids)
                ->where('status', User::STATUS_ACTIVE)
                ->count();
        }

        return [
            'staff' => count($ids),
            'staff_active' => $active,
            'present_today' => $this->distinctClocked($ids, $today),
            'late_today' => $this->distinctLate($ids, $today),
            'open_punches' => $this->openPunches($ids),
            'missing_reports' => $this->missingReports->missingForActor($actor, $now)->count(),
        ];
    }

    /**
     * @param  list<int>  $ids
     */
    private function distinctClocked(array $ids, string $today): int
    {
        if ($ids === []) {
            return 0;
        }

        return (int) Attendance::query()
            ->whereIn('user_id', $ids)
            ->whereDate('date', $today)
            ->whereNotNull('clock_in')
            ->selectRaw('COUNT(DISTINCT user_id) as aggregate_count')
            ->value('aggregate_count');
    }

    /**
     * @param  list<int>  $ids
     */
    private function distinctLate(array $ids, string $today): int
    {
        if ($ids === []) {
            return 0;
        }

        return (int) Attendance::query()
            ->whereIn('user_id', $ids)
            ->whereDate('date', $today)
            ->where(function ($query): void {
                $query->where('late_minutes', '>', 0)
                    ->orWhere('status', Attendance::STATUS_LATE);
            })
            ->selectRaw('COUNT(DISTINCT user_id) as aggregate_count')
            ->value('aggregate_count');
    }

    /**
     * @param  list<int>  $ids
     */
    private function openPunches(array $ids): int
    {
        if ($ids === []) {
            return 0;
        }

        return (int) Attendance::query()
            ->whereIn('user_id', $ids)
            ->whereNotNull('clock_in')
            ->whereNull('clock_out')
            ->count();
    }

    /**
     * @param  list<int>  $ids
     * @return list<array{date: string, label: string, value: int}>
     */
    private function attendanceTrend(array $ids, Carbon $now): array
    {
        $from = $now->copy()->subDays(self::TREND_DAYS - 1)->toDateString();
        $to = $now->toDateString();
        $byDay = [];

        if ($ids !== []) {
            $rows = Attendance::query()
                ->whereIn('user_id', $ids)
                ->whereDate('date', '>=', $from)
                ->whereDate('date', '<=', $to)
                ->whereNotNull('clock_in')
                ->select('date', DB::raw('COUNT(DISTINCT user_id) as present_count'))
                ->groupBy('date')
                ->get();

            foreach ($rows as $row) {
                $byDay[Carbon::parse($row->date)->toDateString()] = (int) $row->present_count;
            }
        }

        $series = [];
        for ($i = self::TREND_DAYS - 1; $i >= 0; $i--) {
            $day = $now->copy()->subDays($i);
            $key = $day->toDateString();
            $series[] = [
                'date' => $key,
                'label' => $day->translatedFormat('d M'),
                'value' => $byDay[$key] ?? 0,
            ];
        }

        return $series;
    }

    /**
     * Company view: all five roles company-wide. Team TL view: roles among scoped staff ids.
     *
     * @param  list<int>  $ids
     * @return list<array{key: string, label: string, value: int, color: string}>
     */
    private function roleSeries(User $actor, array $ids): array
    {
        $teamOnly = $actor->isTeamLeader() && ! $actor->canManageOperations();
        $counts = [];

        if ($teamOnly) {
            if ($ids !== []) {
                $counts = User::query()
                    ->whereIn('id', $ids)
                    ->select('role', DB::raw('COUNT(*) as c'))
                    ->groupBy('role')
                    ->pluck('c', 'role')
                    ->map(fn ($n): int => (int) $n)
                    ->all();
            }
        } else {
            $counts = User::query()
                ->select('role', DB::raw('COUNT(*) as c'))
                ->groupBy('role')
                ->pluck('c', 'role')
                ->map(fn ($n): int => (int) $n)
                ->all();
        }

        $colors = [
            User::ROLE_EMPLOYEE => '#0d6efd',
            User::ROLE_DRIVER => '#20c997',
            User::ROLE_TEAM_LEADER => '#198754',
            User::ROLE_ASSISTANT => '#6f42c1',
            User::ROLE_ACCOUNTANT => '#fd7e14',
            User::ROLE_MANAGER => '#6c757d',
            User::ROLE_SALES => '#e83e8c',
        ];

        $out = [];
        foreach ($colors as $role => $color) {
            $value = $counts[$role] ?? 0;
            if ($teamOnly && $value === 0 && ! in_array($role, User::operationsVisibleRoles(), true)) {
                continue;
            }
            $out[] = [
                'key' => $role,
                'label' => __('employees.roles.'.$role),
                'value' => $value,
                'color' => $color,
            ];
        }

        return $out;
    }

    /**
     * @param  list<int>  $ids
     * @return list<array{key: string, label: string, value: int}>
     */
    private function moduleSeries(User $actor, array $ids): array
    {
        $hr = 0;
        if ($ids !== []) {
            $hr = (int) User::query()
                ->whereIn('id', $ids)
                ->where('status', User::STATUS_ACTIVE)
                ->count();
        }

        $series = [
            ['key' => 'hr', 'label' => __('portal.hr'), 'value' => $hr],
        ];

        if (AppModule::mayAccessModule($actor, AppModule::SERVICE)) {
            $series[] = ['key' => 'service', 'label' => __('portal.service'), 'value' => 0];
        }
        if (AppModule::mayAccessModule($actor, AppModule::PRODUCT)) {
            $series[] = ['key' => 'product', 'label' => __('portal.product'), 'value' => 0];
        }

        return $series;
    }

    /**
     * @param  list<int>  $ids
     * @return array{planned: float, actual: float}
     */
    private function monthHours(array $ids, Carbon $now): array
    {
        if ($ids === []) {
            return ['planned' => 0.0, 'actual' => 0.0];
        }

        $rows = Attendance::query()
            ->with('user')
            ->whereIn('user_id', $ids)
            ->whereYear('date', $now->year)
            ->whereMonth('date', $now->month)
            ->get(['id', 'user_id', 'date', 'shift_type', 'planned_hours', 'actual_hours']);

        $planned = 0.0;
        $actual = 0.0;
        foreach ($rows as $row) {
            $planned += $this->shifts->resolveForAttendance($row);
            $actual += (float) $row->actual_hours;
        }

        return [
            'planned' => round($planned, 2),
            'actual' => round($actual, 2),
        ];
    }
}
