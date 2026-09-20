<?php

namespace App\Http\Controllers\TeamLeader;

use App\Exceptions\PermissionRequestException;
use App\Exceptions\VacationRequestException;
use App\Http\Controllers\Controller;
use App\Models\AttendanceGap;
use App\Models\Permission;
use App\Models\Vacation;
use App\Services\Attendance\AttendanceGapService;
use App\Services\Attendance\AttendanceTimeReviewService;
use App\Services\Permissions\PermissionRequestService;
use App\Services\Vacations\VacationRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * TL / Manager leave+vacation; Manager+Assistant gap finalize (T-2.8 / T-2.11 / R7).
 * No money shown (AGENTS §5.1). Vacation remaining credit is visible.
 */
class ApprovalController extends Controller
{
    public function __construct(
        private readonly PermissionRequestService $permissions,
        private readonly VacationRequestService $vacations,
        private readonly AttendanceGapService $gaps,
        private readonly AttendanceTimeReviewService $overtimeReviews,
    ) {}

    public function index(Request $request): View
    {
        $filter = $request->string('filter')->toString() ?: 'pending';

        if (! in_array($filter, ['pending', 'decided', 'all'], true)) {
            $filter = 'pending';
        }

        $actor = $request->user();
        $canDecideLeave = $actor->isManager() || $actor->isTeamLeader();

        return view('team-leader.approvals.index', [
            'filter' => $filter,
            'permissions' => $canDecideLeave
                ? $this->permissions->listForActor($actor, $filter)
                : new LengthAwarePaginator([], 0, 25),
            'vacations' => $canDecideLeave
                ? $this->vacations->listForActor($actor, $filter)
                : new LengthAwarePaginator([], 0, 25),
            'gaps' => $this->gaps->listForActor($actor, $filter),
            'overtimeRows' => $this->overtimeReviews->listOvertimeForActor($actor, $filter),
            'canForce' => $canDecideLeave,
            'canDecideLeave' => $canDecideLeave,
            'canManageOperations' => $actor->canManageOperations(),
        ]);
    }

    public function approve(Request $request, Permission $permission): RedirectResponse
    {
        abort_unless($this->permissions->actorMayDecide($request->user(), $permission), 403);

        try {
            $this->permissions->approve($request->user(), $permission);
        } catch (PermissionRequestException $e) {
            throw ValidationException::withMessages([
                'permission' => __($e->getMessage(), $e->replace()),
            ]);
        }

        return redirect()
            ->route('team-leader.approvals', ['filter' => $request->input('filter', 'pending')])
            ->with('success', __('permissions.tl.flash.accepted'));
    }

    public function reject(Request $request, Permission $permission): RedirectResponse
    {
        abort_unless($this->permissions->actorMayDecide($request->user(), $permission), 403);

        try {
            $this->permissions->reject($request->user(), $permission);
        } catch (PermissionRequestException $e) {
            throw ValidationException::withMessages([
                'permission' => __($e->getMessage(), $e->replace()),
            ]);
        }

        return redirect()
            ->route('team-leader.approvals', ['filter' => $request->input('filter', 'pending')])
            ->with('success', __('permissions.tl.flash.rejected'));
    }

    public function force(Request $request, Permission $permission): RedirectResponse
    {
        abort_unless($this->permissions->actorMayForce($request->user(), $permission), 403);

        try {
            $this->permissions->force($request->user(), $permission);
        } catch (PermissionRequestException $e) {
            throw ValidationException::withMessages([
                'permission' => __($e->getMessage(), $e->replace()),
            ]);
        }

        return redirect()
            ->route('team-leader.approvals', ['filter' => $request->input('filter', 'decided')])
            ->with('success', __('permissions.tl.flash.forced'));
    }

    public function approveVacation(Request $request, Vacation $vacation): RedirectResponse
    {
        abort_unless($this->vacations->actorMayDecide($request->user(), $vacation), 403);

        try {
            $this->vacations->approve($request->user(), $vacation);
        } catch (VacationRequestException $e) {
            throw ValidationException::withMessages([
                'vacation' => __($e->getMessage()),
            ]);
        }

        return redirect()
            ->route('team-leader.approvals', ['filter' => $request->input('filter', 'pending')])
            ->with('success', __('vacations.tl.flash.accepted'));
    }

    public function rejectVacation(Request $request, Vacation $vacation): RedirectResponse
    {
        abort_unless($this->vacations->actorMayDecide($request->user(), $vacation), 403);

        try {
            $this->vacations->reject($request->user(), $vacation);
        } catch (VacationRequestException $e) {
            throw ValidationException::withMessages([
                'vacation' => __($e->getMessage()),
            ]);
        }

        return redirect()
            ->route('team-leader.approvals', ['filter' => $request->input('filter', 'pending')])
            ->with('success', __('vacations.tl.flash.rejected'));
    }

    public function forceVacation(Request $request, Vacation $vacation): RedirectResponse
    {
        abort_unless($this->vacations->actorMayForce($request->user(), $vacation), 403);

        try {
            $this->vacations->force($request->user(), $vacation);
        } catch (VacationRequestException $e) {
            throw ValidationException::withMessages([
                'vacation' => __($e->getMessage()),
            ]);
        }

        return redirect()
            ->route('team-leader.approvals', ['filter' => $request->input('filter', 'decided')])
            ->with('success', __('vacations.tl.flash.forced'));
    }

    public function recommendGap(Request $request, AttendanceGap $gap): RedirectResponse
    {
        $validated = $request->validate([
            'recommendation' => ['required', 'in:APPROVE,REJECT'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->gaps->tlRecommend(
                $request->user(),
                $gap,
                $validated['recommendation'],
                $validated['note'] ?? null,
            );
        } catch (ValidationException $e) {
            throw $e;
        }

        return redirect()
            ->route('team-leader.approvals', ['filter' => $request->input('filter', 'pending')])
            ->with('success', __('attendance_gaps.flash.tl_recommend'));
    }

    public function decideGap(Request $request, AttendanceGap $gap): RedirectResponse
    {
        $validated = $request->validate([
            'decision' => ['required', 'in:APPROVED,REJECTED'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->gaps->adminDecide(
                $request->user(),
                $gap,
                $validated['decision'],
                $validated['note'] ?? null,
            );
        } catch (ValidationException $e) {
            throw $e;
        }

        return redirect()
            ->route('team-leader.approvals', ['filter' => $request->input('filter', 'pending')])
            ->with('success', __('attendance_gaps.flash.admin_decide'));
    }
}
