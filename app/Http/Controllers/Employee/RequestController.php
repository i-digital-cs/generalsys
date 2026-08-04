<?php

namespace App\Http\Controllers\Employee;

use App\Exceptions\PermissionRequestException;
use App\Exceptions\VacationRequestException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\StorePermissionRequest;
use App\Http\Requests\Employee\StoreVacationRequest;
use App\Models\Permission;
use App\Models\Vacation;
use App\Services\Employees\RestDayService;
use App\Services\Permissions\PermissionRequestService;
use App\Services\Vacations\VacationRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * My Requests — permissions (T-2.7) + vacations (T-2.10).
 */
class RequestController extends Controller
{
    public function __construct(
        private readonly PermissionRequestService $permissions,
        private readonly VacationRequestService $vacations,
        private readonly RestDayService $restDays,
    ) {}

    public function index(Request $request): View
    {
        $tab = $request->string('tab')->toString() ?: 'permissions';

        if (! in_array($tab, ['permissions', 'vacations'], true)) {
            $tab = 'permissions';
        }

        $user = $request->user();

        return view('employee.requests.index', [
            'tab' => $tab,
            'permissions' => $tab === 'permissions'
                ? $this->permissions->listForUser($user)
                : null,
            'vacations' => $tab === 'vacations'
                ? $this->vacations->listForUser($user)
                : null,
            'availableRestDays' => $tab === 'vacations'
                ? $this->restDays->availableForSwap($user)
                : collect(),
        ]);
    }

    public function storePermission(StorePermissionRequest $request): RedirectResponse
    {
        try {
            $this->permissions->submit($request->user(), $request->validated());
        } catch (PermissionRequestException $e) {
            throw ValidationException::withMessages([
                'type' => __($e->getMessage()),
            ]);
        }

        return redirect()
            ->route('employee.requests', ['tab' => 'permissions'])
            ->with('success', __('permissions.flash.submitted'));
    }

    public function forcePermission(Request $request, Permission $permission): RedirectResponse
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
            ->route('employee.requests', ['tab' => 'permissions'])
            ->with('success', __('permissions.flash.forced'));
    }

    public function storeVacation(StoreVacationRequest $request): RedirectResponse
    {
        try {
            $this->vacations->submit($request->user(), $request->validated());
        } catch (VacationRequestException $e) {
            throw ValidationException::withMessages([
                'start_date' => __($e->getMessage()),
            ]);
        }

        return redirect()
            ->route('employee.requests', ['tab' => 'vacations'])
            ->with('success', __('vacations.flash.submitted'));
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
            ->route('employee.requests', ['tab' => 'vacations'])
            ->with('success', __('vacations.flash.forced'));
    }
}
