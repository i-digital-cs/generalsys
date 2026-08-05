<?php

namespace App\Http\Controllers\Employee;

use App\Exceptions\AttendanceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\ClockInRequest;
use App\Models\Attendance;
use App\Services\Attendance\AttendanceService;
use App\Services\Attendance\DailyReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Employee clock screen (T-2.3) — thin: authorize → service → view/redirect.
 */
class ClockController extends Controller
{
    public function __construct(
        private readonly AttendanceService $attendance,
        private readonly DailyReportService $reports,
    ) {}

    public function show(Request $request): View
    {
        $user = $request->user();
        $open = $this->attendance->openAttendance($user);
        $preferredShift = $this->attendance->preferredShiftType($user);
        $scheduleShift = $open?->shift_type ?? $preferredShift;
        $expectedStart = $this->attendance->expectedStartFor($user, $scheduleShift);
        $expectedEnd = $this->attendance->expectedEndFor($user, $scheduleShift);

        $todayAuto = Attendance::query()
            ->where('user_id', $user->id)
            ->whereDate('date', now()->toDateString())
            ->where('is_auto_clock_out', true)
            ->orderByDesc('id')
            ->first();

        return view('employee.clock', [
            'open' => $open,
            'missingReportDate' => $this->reports->latestUnreportedClosedDay($user)?->date->toDateString(),
            'shiftTypes' => $this->attendance->availableShiftTypes($user),
            'preferredShift' => $preferredShift,
            'todaysAssignment' => $this->attendance->todaysPrimaryAssignment($user),
            'expectedStart' => $expectedStart,
            'expectedEnd' => $expectedEnd,
            'previewLateMinutes' => $open === null
                ? $this->attendance->previewLateMinutes($user, $preferredShift)
                : 0,
            'lateMinutes' => $open !== null ? (int) $open->late_minutes : 0,
            'todayAutoClockOut' => $todayAuto,
        ]);
    }

    public function clockIn(ClockInRequest $request): RedirectResponse
    {
        try {
            $this->attendance->clockIn($request->user(), $request->validated('shift_type'));
        } catch (AttendanceException $e) {
            throw ValidationException::withMessages(['shift_type' => __($e->getMessage())]);
        }

        return redirect()
            ->route('employee.clock')
            ->with('success', __('clock.flash.clocked_in'));
    }

    public function clockOut(Request $request): RedirectResponse
    {
        try {
            $attendance = $this->attendance->clockOut($request->user());
        } catch (AttendanceException $e) {
            throw ValidationException::withMessages(['clock_out' => __($e->getMessage())]);
        }

        // After leaving, route the employee straight to their daily report
        // when the day's report is still missing.
        $date = $attendance->date->toDateString();
        $main = $this->reports->mainAttendanceForDate($request->user(), $date);

        if ($main !== null && blank($main->daily_report)) {
            return redirect()
                ->route('employee.report', ['date' => $date])
                ->with('success', __('clock.flash.clocked_out_report'));
        }

        return redirect()
            ->route('employee.clock')
            ->with('success', __('clock.flash.clocked_out'));
    }
}
