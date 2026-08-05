<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Services\Attendance\DailyReportService;
use App\Services\Employee\EmployeeDashboardService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Employee home (T-2.4) — today status + clock CTA + shifts + calendar.
 */
class DashboardController extends Controller
{
    public function __construct(
        private readonly EmployeeDashboardService $dashboard,
        private readonly DailyReportService $reports,
    ) {}

    public function __invoke(Request $request): View
    {
        $snapshot = $this->dashboard->snapshot(
            $request->user(),
            null,
            $request->string('cal')->toString() ?: null,
        );

        $cursor = Carbon::createFromFormat('Y-m', $snapshot['calendar']['cursor'])->startOfMonth();

        return view('employee.dashboard', [
            'open' => $snapshot['open'],
            'missingReportDate' => $this->reports->latestUnreportedClosedDay($request->user())?->date->toDateString(),
            'assignment' => $snapshot['assignment'],
            'preferredShift' => $snapshot['preferredShift'],
            'todaySessions' => $snapshot['todaySessions'],
            'hoursToday' => $snapshot['hoursToday'],
            'isClockedIn' => $snapshot['isClockedIn'],
            'assignedShifts' => $snapshot['assignedShifts'],
            'calendar' => $snapshot['calendar'],
            'hoursMonth' => $snapshot['hoursMonth'],
            'calPrevUrl' => route('employee.dashboard', ['cal' => $cursor->copy()->subMonth()->format('Y-m')]),
            'calNextUrl' => route('employee.dashboard', ['cal' => $cursor->copy()->addMonth()->format('Y-m')]),
        ]);
    }
}
