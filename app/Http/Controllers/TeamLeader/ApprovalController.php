<?php

namespace App\Http\Controllers\TeamLeader;

use App\Exceptions\PermissionRequestException;
use App\Exceptions\VacationRequestException;
use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Vacation;
use App\Services\Permissions\PermissionRequestService;
use App\Services\Vacations\VacationRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * TL / Manager approvals for permissions (T-2.8) and vacations (T-2.11).
 * No money shown (AGENTS §5.1). Vacation remaining credit is visible.
 */
class ApprovalController extends Controller
{
    public function __construct(
        private readonly PermissionRequestService $permissions,
        private readonly VacationRequestService $vacations,
    ) {}

    public function index(Request $request): View
    {
        $filter = $request->string('filter')->toString() ?: 'pending';

        if (! in_array($filter, ['pending', 'decided', 'all'], true)) {
            $filter = 'pending';
        }

        $actor = $request->user();

        return view('team-leader.approvals.index', [
            'filter' => $filter,
            'permissions' => $this->permissions->listForActor($actor, $filter),
            'vacations' => $this->vacations->listForActor($actor, $filter),
            'canForce' => $actor->isManager() || $actor->isTeamLeader(),
        ]);
    }

    public function approve(Request $request, Permission $permission): RedirectResponse
    {
        abort_unless($this->permissions->actorMayDecide($request->user(), $permission), 403);

        try {
            $this->permissions->approve($request->user(), $permission);
        } catch (PermissionRequestException $e) {
            throw ValidationException::withMessages([
                'permission' => __($e->getMessage()),
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
                'permission' => __($e->getMessage()),
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
                'permission' => __($e->getMessage()),
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
}
