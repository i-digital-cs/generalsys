<?php

namespace App\Http\Controllers\Gross;

use App\Exceptions\GrossException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gross\StoreGrossTreasuryVoucherRequest;
use App\Models\Gross\GrossTreasuryVoucher;
use App\Services\Gross\ExpenseTypeService;
use App\Services\Gross\SupplierService;
use App\Services\Gross\TreasuryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TreasuryController extends Controller
{
    public function __construct(
        private readonly TreasuryService $treasury,
        private readonly SupplierService $suppliers,
        private readonly ExpenseTypeService $expenseTypes,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', GrossTreasuryVoucher::class);

        $filters = [
            'type' => trim((string) $request->query('type', '')),
            'purpose' => trim((string) $request->query('purpose', '')),
            'account_id' => $request->query('account_id'),
            'expense_type_id' => $request->query('expense_type_id'),
        ];

        return view('gross.treasury.index', [
            'accounts' => $this->treasury->accountsWithBalances(),
            'vouchers' => $this->treasury->paginateVouchers($filters),
            'filters' => $filters,
            'filterAccounts' => $this->treasury->activeAccounts(),
            'filterExpenseTypes' => $this->expenseTypes->activeForForms(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', GrossTreasuryVoucher::class);

        $type = strtoupper((string) $request->query('type', GrossTreasuryVoucher::TYPE_RECEIPT));
        if (! in_array($type, [GrossTreasuryVoucher::TYPE_PAYMENT, GrossTreasuryVoucher::TYPE_RECEIPT], true)) {
            $type = GrossTreasuryVoucher::TYPE_RECEIPT;
        }

        $purpose = strtoupper((string) $request->query('purpose', GrossTreasuryVoucher::PURPOSE_EXPENSE));
        if (! in_array($purpose, [
            GrossTreasuryVoucher::PURPOSE_SUPPLIER,
            GrossTreasuryVoucher::PURPOSE_EXPENSE,
        ], true)) {
            $purpose = GrossTreasuryVoucher::PURPOSE_EXPENSE;
        }

        $supplierId = old('supplier_id', $request->query('supplier_id'));
        $expenseTypeId = old('expense_type_id', $request->query('expense_type_id'));

        return view('gross.treasury.create', [
            'type' => $type,
            'purpose' => old('purpose', $purpose),
            'selectedSupplierId' => $supplierId !== null && $supplierId !== '' ? (int) $supplierId : null,
            'selectedExpenseTypeId' => $expenseTypeId !== null && $expenseTypeId !== '' ? (int) $expenseTypeId : null,
            'accounts' => $this->treasury->activeAccounts(),
            'suppliers' => $this->suppliers->listForForms(200),
            'expenseTypes' => $this->expenseTypes->activeForForms(),
        ]);
    }

    public function store(StoreGrossTreasuryVoucherRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $type = $data['type'];
        unset($data['type']);

        try {
            $voucher = $type === GrossTreasuryVoucher::TYPE_PAYMENT
                ? $this->treasury->createPayment($request->user(), $data)
                : $this->treasury->createReceipt($request->user(), $data);
        } catch (GrossException $e) {
            return back()->withInput()->withErrors(['amount' => __($e->getMessage())]);
        }

        return redirect()
            ->route('gross.treasury.show', $voucher)
            ->with('success', __('gross.flash.voucher_created'));
    }

    public function show(GrossTreasuryVoucher $voucher): View
    {
        $this->authorize('view', $voucher);
        $voucher->load(['account', 'supplier', 'expenseType', 'creator']);

        return view('gross.treasury.show', compact('voucher'));
    }
}
