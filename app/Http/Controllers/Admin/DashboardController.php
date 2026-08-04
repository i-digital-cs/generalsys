<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Admin\DashboardStatsService;
use App\Services\Attendance\HoursComparisonService;
use App\Services\Dashboard\DashboardCalendarService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardStatsService $stats,
        private readonly DashboardCalendarService $calendar,
        private readonly HoursComparisonService $hours,
    ) {}

    public function index(Request $request): View
    {
        $focusUserId = $request->filled('user_id') ? (int) $request->input('user_id') : null;

        if ($focusUserId !== null) {
            $focus = User::query()->findOrFail($focusUserId);
            abort_unless($this->hours->actorMayView($request->user(), $focus), 403);
        }

        $calData = $this->calendar->forManager(
            $request->user(),
            $request->string('cal')->toString() ?: null,
            $focusUserId,
        );
        $cursor = Carbon::createFromFormat('Y-m', $calData['calendar']['cursor'])->startOfMonth();
        $queryBase = [];
        $focusId = $calData['focusUser']->id ?? $focusUserId;
        if ($focusId !== null) {
            $queryBase['user_id'] = $focusId;
        }

        return view('admin.dashboard', [
            'stats' => $this->stats->stats(),
            'expiringContracts' => $this->stats->expiringContracts(),
            'calendar' => $calData['calendar'],
            'teamShifts' => $calData['teamShifts'],
            'showTeamHours' => true,
            'hoursMonth' => $calData['hoursMonth'],
            'teamSummary' => $calData['teamSummary'],
            'todayStatus' => $calData['todayStatus'],
            'focusUser' => $calData['focusUser'],
            'teamMembers' => $calData['teamMembers'],
            'focusOptions' => $calData['focusOptions'],
            'calPrevUrl' => route('admin.dashboard', array_merge($queryBase, [
                'cal' => $cursor->copy()->subMonth()->format('Y-m'),
            ])),
            'calNextUrl' => route('admin.dashboard', array_merge($queryBase, [
                'cal' => $cursor->copy()->addMonth()->format('Y-m'),
            ])),
            'dashboardRoute' => 'admin.dashboard',
            'calCursor' => $calData['calendar']['cursor'],
        ]);
    }
}
