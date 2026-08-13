<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Attendance\HoursComparisonService;
use App\Services\Attendance\MissingDailyReportService;
use App\Services\Dashboard\DashboardCalendarService;
use App\Services\Dashboard\DashboardService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * TEAM_LEADER home (T-2.13 team attendance) — also used as team-leader.dashboard.
 */
class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboard,
        private readonly DashboardCalendarService $calendar,
        private readonly HoursComparisonService $hours,
        private readonly MissingDailyReportService $missingReports,
    ) {}

    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $cal = $request->string('cal')->toString() ?: null;
        $focusUserId = $request->filled('user_id') ? (int) $request->input('user_id') : null;

        if ($focusUserId !== null) {
            $focus = User::query()->findOrFail($focusUserId);
            abort_unless($this->hours->actorMayView($user, $focus), 403);
        }

        if ($user->isTeamLeader()) {
            $calData = $this->calendar->forTeamLeader($user, $cal, $focusUserId);
        } elseif ($user->isManager()) {
            $calData = $this->calendar->forManager($user, $cal, $focusUserId);
        } else {
            $calData = $this->calendar->holidaysOnly($cal);
        }

        $cursor = Carbon::createFromFormat('Y-m', $calData['calendar']['cursor'])->startOfMonth();
        $queryBase = [];
        $focusId = $calData['focusUser']->id ?? $focusUserId;
        if ($focusId !== null) {
            $queryBase['user_id'] = $focusId;
        }

        $routeName = $request->routeIs('team-leader.dashboard')
            ? 'team-leader.dashboard'
            : 'dashboard';

        $today = now();
        $missingReportsToday = collect();
        $missingReportsOverdue = collect();
        if ($user->isTeamLeader() || $user->isManager()) {
            $missingReportsToday = $this->missingReports->missingForActor($user, $today);
            $missingReportsOverdue = $this->missingReports->missingForActor($user, $today->copy()->subDay());
        }

        return view('dashboard', [
            'quickActions' => $this->dashboard->quickActionsFor($user->role),
            'upcomingHolidays' => $this->dashboard->upcomingHolidays(),
            'calendar' => $calData['calendar'],
            'teamShifts' => $calData['teamShifts'] ?? collect(),
            'showTeamHours' => $user->isTeamLeader() || $user->isManager(),
            'hoursMonth' => $calData['hoursMonth'] ?? null,
            'teamSummary' => $calData['teamSummary'] ?? collect(),
            'todayStatus' => $calData['todayStatus'] ?? collect(),
            'focusUser' => $calData['focusUser'] ?? null,
            'teamMembers' => $calData['teamMembers'] ?? collect(),
            'focusOptions' => $calData['focusOptions'] ?? collect([$user]),
            'calPrevUrl' => route($routeName, array_merge($queryBase, [
                'cal' => $cursor->copy()->subMonth()->format('Y-m'),
            ])),
            'calNextUrl' => route($routeName, array_merge($queryBase, [
                'cal' => $cursor->copy()->addMonth()->format('Y-m'),
            ])),
            'dashboardRoute' => $routeName,
            'calCursor' => $calData['calendar']['cursor'],
            'missingReportsToday' => $missingReportsToday,
            'missingReportsOverdue' => $missingReportsOverdue,
            'missingReportsTodayDate' => $today,
            'missingReportsOverdueDate' => $today->copy()->subDay(),
        ]);
    }
}
