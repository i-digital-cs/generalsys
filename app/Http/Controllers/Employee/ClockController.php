<?php

namespace App\Http\Controllers\Employee;

use App\Exceptions\AttendanceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\ClockInRequest;
use App\Services\Attendance\AttendanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Employee clock screen (T-2.3) — thin: authorize → service → view/redirect.
 */
class ClockController extends Controller
{
    public function __construct(private readonly AttendanceService $attendance) {}

    public function show(Request $request): View
    {
        $user = $request->user();

        return view('employee.clock', [
            'open' => $this->attendance->openAttendance($user),
            'shiftTypes' => $this->attendance->availableShiftTypes($user),
            'preferredShift' => $this->attendance->preferredShiftType($user),
            'todaysAssignment' => $this->attendance->todaysPrimaryAssignment($user),
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
            $this->attendance->clockOut($request->user());
        } catch (AttendanceException $e) {
            throw ValidationException::withMessages(['clock_out' => __($e->getMessage())]);
        }

        return redirect()
            ->route('employee.clock')
            ->with('success', __('clock.flash.clocked_out'));
    }
}
