<?php

namespace App\Http\Controllers\Employee;

use App\Exceptions\DailyReportException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\StoreDailyReportRequest;
use App\Services\Attendance\DailyReportService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Employee daily report (T-2.5) — thin: authorize → service → view.
 */
class DailyReportController extends Controller
{
    public function __construct(private readonly DailyReportService $reports) {}

    public function show(Request $request): View
    {
        $date = $request->filled('date')
            ? Carbon::parse($request->string('date')->toString())
            : now();

        $snapshot = $this->reports->snapshot($request->user(), $date);

        return view('employee.report', $snapshot);
    }

    public function store(StoreDailyReportRequest $request): RedirectResponse
    {
        try {
            $this->reports->submit(
                $request->user(),
                $request->validated('date'),
                $request->validated('daily_report'),
            );
        } catch (DailyReportException $e) {
            throw ValidationException::withMessages([
                'daily_report' => __($e->getMessage()),
            ]);
        }

        return redirect()
            ->route('employee.report', ['date' => $request->validated('date')])
            ->with('success', __('report.flash.saved'));
    }
}
