<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\DashboardStatsService;
use App\Services\Dashboard\DashboardCalendarService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardStatsService $stats,
        private readonly DashboardCalendarService $calendar,
    ) {}

    public function index(Request $request): View
    {
        $calData = $this->calendar->forManager($request->string('cal')->toString() ?: null);
        $cursor = Carbon::createFromFormat('Y-m', $calData['calendar']['cursor'])->startOfMonth();

        return view('admin.dashboard', [
            'stats' => $this->stats->stats(),
            'expiringContracts' => $this->stats->expiringContracts(),
            'calendar' => $calData['calendar'],
            'teamShifts' => $calData['teamShifts'],
            'calPrevUrl' => route('admin.dashboard', ['cal' => $cursor->copy()->subMonth()->format('Y-m')]),
            'calNextUrl' => route('admin.dashboard', ['cal' => $cursor->copy()->addMonth()->format('Y-m')]),
        ]);
    }
}
