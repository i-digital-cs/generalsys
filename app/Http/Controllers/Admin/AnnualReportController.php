<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Reports\ReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin annual report (T-5.1) — year grid + evaluation average.
 */
class AnnualReportController extends Controller
{
    public function __construct(private readonly ReportService $reports) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()?->isManager(), 403);

        $year = max(2020, min(2100, (int) $request->input('year', now()->year)));
        $userId = $request->filled('user_id') ? (int) $request->input('user_id') : null;
        $employees = $this->reports->eligibleEmployees();

        $report = null;
        if ($userId !== null) {
            $employee = User::query()
                ->with(['activeContract', 'contracts'])
                ->findOrFail($userId);
            abort_unless(
                in_array($employee->role, User::operationsVisibleRoles(), true),
                404,
            );
            $report = $this->reports->annualReport($employee, $year);
        }

        return view('admin.reports.annual', [
            'year' => $year,
            'userId' => $userId,
            'employees' => $employees,
            'report' => $report,
        ]);
    }
}
