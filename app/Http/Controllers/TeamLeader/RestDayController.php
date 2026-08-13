<?php

namespace App\Http\Controllers\TeamLeader;

use App\Http\Controllers\Controller;
use App\Http\Requests\TeamLeader\StoreRestDayRequest;
use App\Models\RestDay;
use App\Models\User;
use App\Services\Employees\RestDayService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Date-based rest days for team (Admin + TL). */
class RestDayController extends Controller
{
    public function __construct(private readonly RestDayService $restDays) {}

    public function index(Request $request): View
    {
        return view('team-leader.rest-days.index', [
            'employees' => $this->restDays->manageableEmployees($request->user()),
        ]);
    }

    public function store(StoreRestDayRequest $request, User $employee): RedirectResponse
    {
        /** @var list<string> $dates */
        $dates = $request->validated('dates');

        $result = $this->restDays->addMany($request->user(), $employee, $dates);

        $message = $result['skipped'] > 0
            ? __('employees.rest_days.flash.added_many_skipped', [
                'added' => $result['added'],
                'skipped' => $result['skipped'],
            ])
            : __('employees.rest_days.flash.added_many', [
                'added' => $result['added'],
            ]);

        return redirect()
            ->route('team-leader.rest-days')
            ->with('success', $message);
    }

    public function destroy(Request $request, User $employee, RestDay $restDay): RedirectResponse
    {
        abort_unless((int) $restDay->user_id === (int) $employee->id, 404);

        $this->restDays->remove($request->user(), $restDay);

        return redirect()
            ->route('team-leader.rest-days')
            ->with('success', __('employees.rest_days.flash.removed'));
    }
}
