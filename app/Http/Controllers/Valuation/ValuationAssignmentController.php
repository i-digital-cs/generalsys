<?php

namespace App\Http\Controllers\Valuation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Valuation\SyncValuationAssignmentRequest;
use App\Models\User;
use App\Models\ValuationAssignment;
use App\Services\Valuation\ValuationAssignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ValuationAssignmentController extends Controller
{
    public function __construct(private readonly ValuationAssignmentService $assignments) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', ValuationAssignment::class);

        return view('valuation.assignments.index', [
            'users' => $this->assignments->paginateAssignees(),
        ]);
    }

    public function update(SyncValuationAssignmentRequest $request, User $user): RedirectResponse
    {
        $this->authorize('update', ValuationAssignment::class);
        $this->assignments->syncForUser($request->user(), $user, $request->validated('modules') ?? []);

        return redirect()
            ->route('valuation.assignments.index')
            ->with('success', __('valuation.flash.assigned'));
    }
}
