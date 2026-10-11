<?php

namespace App\Http\Controllers\TeamLeader;

use App\Exceptions\AttendanceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\TeamLeader\ForceClockOutRequest;
use App\Models\Attendance;
use App\Services\Approvals\ApprovalsListFilters;
use App\Services\Attendance\AttendanceService;
use App\Services\Attendance\AttendanceTimeReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * TL/Admin manual credited hours, late override, overtime (T-9.1).
 */
class AttendanceReviewController extends Controller
{
    public function __construct(
        private readonly AttendanceTimeReviewService $reviews,
        private readonly AttendanceService $attendance,
    ) {}

    public function updateTime(Request $request, Attendance $attendance): RedirectResponse
    {
        try {
            if ($request->boolean('reset_system')) {
                $this->reviews->applyAdjustments($request->user(), $attendance, [
                    'credited_hours' => null,
                    'adjusted_late_minutes' => null,
                ]);

                return back()->with('success', __('attendance_review.flash.reset'));
            }

            $validated = $request->validate([
                'credited_hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
                'adjusted_late_minutes' => ['nullable', 'integer', 'min:0', 'max:720'],
            ]);

            $this->reviews->applyAdjustments($request->user(), $attendance, $validated);
        } catch (ValidationException $e) {
            throw $e;
        }

        return back()->with('success', __('attendance_review.flash.saved'));
    }

    public function tlRecommendOvertime(Request $request, Attendance $attendance): RedirectResponse
    {
        $validated = $request->validate([
            'recommended_overtime_minutes' => ['required', 'integer', 'min:0', 'max:720'],
            'overtime_tl_recommendation' => ['required', 'in:RECOMMEND,NO_OVERTIME'],
        ]);

        try {
            $this->reviews->tlRecommendOvertime(
                $request->user(),
                $attendance,
                (int) $validated['recommended_overtime_minutes'],
                $validated['overtime_tl_recommendation'],
            );
        } catch (ValidationException $e) {
            throw $e;
        }

        if ($request->filled('filter')) {
            return redirect()
                ->route('team-leader.approvals', ApprovalsListFilters::fromRequest($request)->queryParams())
                ->with('success', __('attendance_review.flash.overtime_tl'));
        }

        return back()->with('success', __('attendance_review.flash.overtime_tl'));
    }

    public function adminDecideOvertime(Request $request, Attendance $attendance): RedirectResponse
    {
        $validated = $request->validate([
            'overtime_admin_decision' => ['required', 'in:APPROVED,REJECTED'],
        ]);

        try {
            $this->reviews->adminDecideOvertime(
                $request->user(),
                $attendance,
                $validated['overtime_admin_decision'],
            );
        } catch (ValidationException $e) {
            throw $e;
        }

        if ($request->filled('filter')) {
            return redirect()
                ->route('team-leader.approvals', ApprovalsListFilters::fromRequest($request)->queryParams())
                ->with('success', __('attendance_review.flash.overtime_admin'));
        }

        return back()->with('success', __('attendance_review.flash.overtime_admin'));
    }

    public function forceClockOut(ForceClockOutRequest $request, Attendance $attendance): RedirectResponse
    {
        try {
            $this->attendance->supervisorClockOut($attendance, $request->user());
        } catch (AttendanceException $e) {
            return back()->with('error', __($e->getMessage()));
        }

        return back()->with('success', __('attendance_review.flash.force_clock_out'));
    }
}
