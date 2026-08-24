<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAdvanceRequest;
use App\Http\Requests\Admin\UpdateAdvanceRequest;
use App\Models\Advance;
use App\Models\User;
use App\Services\Payroll\AdvanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin advances CRUD (T-4.8) — Manager only.
 */
class AdvanceController extends Controller
{
    public function __construct(private readonly AdvanceService $advances) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Advance::class);

        $filters = [
            'user_id' => $request->integer('user_id') ?: null,
            'status' => $request->input('status'),
        ];

        return view('admin.advances.index', [
            'advances' => $this->advances->listForAdmin(array_filter($filters))->withQueryString(),
            'employees' => User::query()
                ->whereIn('role', [User::ROLE_EMPLOYEE, User::ROLE_DRIVER, User::ROLE_TEAM_LEADER, User::ROLE_ACCOUNTANT, User::ROLE_ASSISTANT])
                ->where('status', User::STATUS_ACTIVE)
                ->orderBy('name_en')
                ->get(['id', 'name_ar', 'name_en']),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Advance::class);

        return view('admin.advances.create', [
            'employees' => User::query()
                ->whereIn('role', [User::ROLE_EMPLOYEE, User::ROLE_DRIVER, User::ROLE_TEAM_LEADER, User::ROLE_ACCOUNTANT, User::ROLE_ASSISTANT])
                ->where('status', User::STATUS_ACTIVE)
                ->orderBy('name_en')
                ->get(['id', 'name_ar', 'name_en']),
        ]);
    }

    public function store(StoreAdvanceRequest $request): RedirectResponse
    {
        $this->advances->create($request->validated());

        return redirect()
            ->route('admin.advances.index')
            ->with('success', __('advances.flash.created'));
    }

    public function edit(Advance $advance): View
    {
        $this->authorize('update', $advance);

        $advance->load('user');

        return view('admin.advances.edit', [
            'advance' => $advance,
        ]);
    }

    public function update(UpdateAdvanceRequest $request, Advance $advance): RedirectResponse
    {
        $this->advances->update($advance, $request->validated());

        return redirect()
            ->route('admin.advances.index')
            ->with('success', __('advances.flash.updated'));
    }
}
