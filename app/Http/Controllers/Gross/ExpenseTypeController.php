<?php

namespace App\Http\Controllers\Gross;

use App\Exceptions\GrossException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gross\StoreGrossExpenseTypeRequest;
use App\Http\Requests\Gross\UpdateGrossExpenseTypeRequest;
use App\Models\Gross\GrossExpenseType;
use App\Services\Gross\ExpenseTypeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExpenseTypeController extends Controller
{
    public function __construct(private readonly ExpenseTypeService $expenseTypes) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', GrossExpenseType::class);

        $q = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', ''));
        $filters = ['q' => $q, 'status' => $status];

        return view('gross.expense-types.index', [
            'types' => $this->expenseTypes->paginate($filters),
            'filters' => $filters,
            'summary' => $this->expenseTypes->summary(),
        ]);
    }

    public function store(StoreGrossExpenseTypeRequest $request): RedirectResponse
    {
        $this->expenseTypes->create($request->validated());

        return redirect()
            ->route('gross.expense-types.index')
            ->with('success', __('gross.flash.expense_type_created'));
    }

    public function update(UpdateGrossExpenseTypeRequest $request, GrossExpenseType $expenseType): RedirectResponse
    {
        $this->expenseTypes->update($expenseType, $request->validated());

        return redirect()
            ->route('gross.expense-types.index', array_filter([
                'q' => $request->query('q'),
                'status' => $request->query('status'),
            ]))
            ->with('success', __('gross.flash.expense_type_updated'));
    }

    public function destroy(GrossExpenseType $expenseType): RedirectResponse
    {
        $this->authorize('delete', $expenseType);

        try {
            $this->expenseTypes->delete($expenseType);
        } catch (GrossException $e) {
            return back()->withErrors(['expense_type' => __($e->getMessage())]);
        }

        return redirect()
            ->route('gross.expense-types.index')
            ->with('success', __('gross.flash.expense_type_deleted'));
    }
}
