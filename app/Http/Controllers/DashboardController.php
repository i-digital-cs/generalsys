<?php

namespace App\Http\Controllers;

use App\Services\Dashboard\DashboardService;
use Illuminate\View\View;

/**
 * Generic authenticated landing page (TEAM_LEADER until the Phase-2
 * team dashboard ships; MANAGER/EMPLOYEE are redirected elsewhere).
 */
class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard) {}

    public function __invoke(): View
    {
        return view('dashboard', [
            'quickActions' => $this->dashboard->quickActionsFor(auth()->user()->role),
            'upcomingHolidays' => $this->dashboard->upcomingHolidays(),
        ]);
    }
}
