<?php

namespace App\Http\Controllers\ProductExpense;

use App\Exceptions\GrossException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProductExpense\StoreProductExpensePaymentRequest;
use App\Http\Requests\ProductExpense\UpsertProductExpenseBudgetRequest;
use App\Models\Gross\GrossExpenseType;
use App\Services\ProductExpense\ProductExpenseService;
use App\Support\GrossAccess;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductExpenseController extends Controller
{
    public function __construct(private readonly ProductExpenseService $expenses) {}

    public function show(Request $request, GrossExpenseType $expenseType): View
    {
        abort_unless(GrossAccess::mayAccessGross($request->user()), 403);

        $year = $request->integer('year') ?: null;
        $month = $request->integer('month') ?: null;
        $detail = $this->expenses->categoryMonthDetail(
            $expenseType,
            $year !== 0 ? $year : null,
            $month !== 0 ? $month : null,
        );

        return view('product-expenses.show', [
            'detail' => $detail,
            'canManage' => GrossAccess::mayManageMasterData($request->user()),
        ]);
    }

    public function upsertBudget(UpsertProductExpenseBudgetRequest $request, GrossExpenseType $expenseType): RedirectResponse
    {
        $data = $request->validated();
        $this->expenses->upsertBudget(
            $request->user(),
            $expenseType,
            (int) $data['year'],
            (int) $data['month'],
            $data,
        );

        return redirect()
            ->route('product.expenses.show', [
                'expenseType' => $expenseType,
                'year' => $data['year'],
                'month' => $data['month'],
            ])
            ->with('success', __('product_expenses.flash.budget_saved'));
    }

    public function storePayment(StoreProductExpensePaymentRequest $request, GrossExpenseType $expenseType): RedirectResponse
    {
        $data = $request->validated();

        try {
            $voucher = $this->expenses->addExpensePayment($request->user(), $expenseType, $data);
        } catch (GrossException $e) {
            return back()->withInput()->withErrors(['amount' => __($e->getMessage())]);
        }

        $date = Carbon::parse($data['voucher_date']);

        return redirect()
            ->route('product.expenses.show', [
                'expenseType' => $expenseType,
                'year' => $date->year,
                'month' => $date->month,
            ])
            ->with('success', __('product_expenses.flash.entry_added', ['number' => $voucher->voucher_number]));
    }
}
