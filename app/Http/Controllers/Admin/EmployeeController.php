<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreEmployeeRequest;
use App\Http\Requests\Admin\UpdateEmployeeRequest;
use App\Models\User;
use App\Services\Employees\EmployeeService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    public function __construct(private readonly EmployeeService $employees) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        return view('admin.employees.index', [
            'employees' => $this->employees->paginate(
                $request->string('q')->toString() ?: null,
                $request->string('status')->toString() ?: null,
            ),
            'q' => $request->string('q')->toString(),
            'status' => $request->string('status')->toString(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('admin.employees.create', [
            'teamLeaders' => $this->teamLeaders(),
            'nextEmployeeCode' => $this->employees->nextEmployeeCode(),
        ]);
    }

    public function store(StoreEmployeeRequest $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        $employee = $this->employees->create($request->validated(), $request->file('photo'));

        return redirect()
            ->route('admin.employees.edit', $employee)
            ->with('success', __('employees.flash.created'));
    }

    public function edit(User $employee): View
    {
        $this->authorize('update', $employee);

        $employee->load([
            'profile', 'activeContract', 'qualifications', 'emergencyContacts', 'documents',
            'restDays' => fn ($query) => $query->orderBy('date'),
        ]);

        return view('admin.employees.edit', [
            'employee' => $employee,
            'teamLeaders' => $this->teamLeaders(),
        ]);
    }

    public function update(UpdateEmployeeRequest $request, User $employee): RedirectResponse
    {
        $this->authorize('update', $employee);

        $this->employees->update($employee, $request->validated(), $request->file('photo'));

        return redirect()
            ->route('admin.employees.edit', $employee)
            ->with('success', __('employees.flash.updated'));
    }

    /**
     * @return Collection<int, User>
     */
    private function teamLeaders()
    {
        return User::where('role', User::ROLE_TEAM_LEADER)->orderBy('name_en')->get(['id', 'name_ar', 'name_en']);
    }
}
