<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRestDayRequest;
use App\Models\RestDay;
use App\Models\User;
use App\Services\Employees\RestDayService;
use Illuminate\Http\RedirectResponse;

/** Rest-day marking on employee edit (Manager). */
class RestDayController extends Controller
{
    public function __construct(private readonly RestDayService $restDays) {}

    public function store(StoreRestDayRequest $request, User $employee): RedirectResponse
    {
        $this->restDays->add(
            $request->user(),
            $employee,
            $request->string('date')->toString(),
        );

        return redirect()
            ->route('admin.employees.edit', $employee)
            ->with('success', __('employees.rest_days.flash.added'));
    }

    public function destroy(User $employee, RestDay $restDay): RedirectResponse
    {
        abort_unless((int) $restDay->user_id === (int) $employee->id, 404);

        $this->restDays->remove(request()->user(), $restDay);

        return redirect()
            ->route('admin.employees.edit', $employee)
            ->with('success', __('employees.rest_days.flash.removed'));
    }
}
