<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePenaltyRuleRequest;
use App\Http\Requests\Admin\UpdatePenaltyRuleRequest;
use App\Models\PenaltyRule;
use App\Services\Payroll\PenaltyRuleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Admin penalty rules grid (T-4.2) — Manager only; TL 403 via middleware + policy.
 */
class PenaltyRuleController extends Controller
{
    public function __construct(private readonly PenaltyRuleService $rules) {}

    public function index(): View
    {
        $this->authorize('viewAny', PenaltyRule::class);

        return view('admin.penalties.index', [
            'rules' => $this->rules->listAll(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', PenaltyRule::class);

        return view('admin.penalties.create');
    }

    public function store(StorePenaltyRuleRequest $request): RedirectResponse
    {
        $this->rules->create($request->validated());

        return redirect()
            ->route('admin.config.penalties')
            ->with('success', __('penalties.flash.created'));
    }

    public function edit(PenaltyRule $penaltyRule): View
    {
        $this->authorize('update', $penaltyRule);

        return view('admin.penalties.edit', [
            'rule' => $penaltyRule,
        ]);
    }

    public function update(UpdatePenaltyRuleRequest $request, PenaltyRule $penaltyRule): RedirectResponse
    {
        $this->rules->update($penaltyRule, $request->validated());

        return redirect()
            ->route('admin.config.penalties')
            ->with('success', __('penalties.flash.updated'));
    }
}
