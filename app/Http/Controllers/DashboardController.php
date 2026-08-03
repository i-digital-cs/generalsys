<?php

namespace App\Http\Controllers;

use App\Services\Dashboard\DashboardCalendarService;
use App\Services\Dashboard\DashboardService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Generic authenticated landing page (TEAM_LEADER until the Phase-2
 * team dashboard ships; MANAGER/EMPLOYEE are redirected elsewhere).
 */
class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboard,
        private readonly DashboardCalendarService $calendar,
    ) {}

    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $cal = $request->string('cal')->toString() ?: null;

        if ($user->isTeamLeader()) {
            $calData = $this->calendar->forTeamLeader($user, $cal);
        } elseif ($user->isManager()) {
            $calData = $this->calendar->forManager($cal);
        } else {
            $calData = $this->calendar->holidaysOnly($cal);
        }

        $cursor = Carbon::createFromFormat('Y-m', $calData['calendar']['cursor'])->startOfMonth();

        return view('dashboard', [
            'quickActions' => $this->dashboard->quickActionsFor($user->role),
            'upcomingHolidays' => $this->dashboard->upcomingHolidays(),
            'calendar' => $calData['calendar'],
            'teamShifts' => $calData['teamShifts'] ?? new Collection,
            'showTeamIntervals' => $user->isTeamLeader() || $user->isManager(),
            'calPrevUrl' => route('dashboard', ['cal' => $cursor->copy()->subMonth()->format('Y-m')]),
            'calNextUrl' => route('dashboard', ['cal' => $cursor->copy()->addMonth()->format('Y-m')]),
        ]);
    }
}
