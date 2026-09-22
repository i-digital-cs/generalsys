<?php

namespace App\Http\Controllers\Gross;

use App\Exceptions\GrossException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gross\StoreGrossTreasuryVoucherRequest;
use App\Models\Gross\GrossTreasuryVoucher;
use App\Models\Gross\GrossTreasuryVoucherAttachment;
use App\Services\Gross\ExpenseTypeService;
use App\Services\Gross\ReceiptPurposeService;
use App\Services\Gross\SalesInvoiceService;
use App\Services\Gross\SupplierService;
use App\Services\Gross\SupplierStatementService;
use App\Services\Gross\TreasuryService;
use App\Services\Gross\TreasuryVoucherAttachmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TreasuryController extends Controller
{
    public function __construct(
        private readonly TreasuryService $treasury,
        private readonly SupplierService $suppliers,
        private readonly ExpenseTypeService $expenseTypes,
        private readonly ReceiptPurposeService $receiptPurposes,
        private readonly SalesInvoiceService $salesInvoices,
        private readonly SupplierStatementService $supplierStatements,
        private readonly TreasuryVoucherAttachmentService $attachments,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', GrossTreasuryVoucher::class);

        $filters = [
            'type' => trim((string) $request->query('type', '')),
            'purpose' => trim((string) $request->query('purpose', '')),
            'account_id' => $request->query('account_id'),
            'expense_type_id' => $request->query('expense_type_id'),
            'receipt_purpose_id' => $request->query('receipt_purpose_id'),
        ];

        $accounts = $this->treasury->accountsWithBalances();

        return view('gross.treasury.index', [
            'accounts' => $accounts,
            'combinedTotals' => $this->treasury->combinedTotalsByCurrency($accounts),
            'vouchers' => $this->treasury->paginateVouchers($filters),
            'filters' => $filters,
            'filterAccounts' => $this->treasury->activeAccounts(),
            'filterExpenseTypes' => $this->expenseTypes->activeForForms(),
            'filterReceiptPurposes' => $this->receiptPurposes->activeForForms(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', GrossTreasuryVoucher::class);

        $type = strtoupper((string) $request->query('type', GrossTreasuryVoucher::TYPE_RECEIPT));
        if (! in_array($type, [GrossTreasuryVoucher::TYPE_PAYMENT, GrossTreasuryVoucher::TYPE_RECEIPT], true)) {
            $type = GrossTreasuryVoucher::TYPE_RECEIPT;
        }

        $receiptPurposes = $this->receiptPurposes->activeForForms();
        $receiptPurposeId = old('receipt_purpose_id', $request->query('receipt_purpose_id'));

        $defaultPurpose = $type === GrossTreasuryVoucher::TYPE_RECEIPT
            ? GrossTreasuryVoucher::PURPOSE_OTHER
            : GrossTreasuryVoucher::PURPOSE_EXPENSE;
        $purpose = strtoupper((string) $request->query('purpose', $defaultPurpose));
        if (! in_array($purpose, [
            GrossTreasuryVoucher::PURPOSE_SUPPLIER,
            GrossTreasuryVoucher::PURPOSE_EXPENSE,
            GrossTreasuryVoucher::PURPOSE_COURIER_COD,
            GrossTreasuryVoucher::PURPOSE_OTHER,
        ], true)) {
            $purpose = $defaultPurpose;
        }

        if ($type === GrossTreasuryVoucher::TYPE_PAYMENT
            && in_array($purpose, [
                GrossTreasuryVoucher::PURPOSE_COURIER_COD,
                GrossTreasuryVoucher::PURPOSE_OTHER,
            ], true)) {
            $purpose = GrossTreasuryVoucher::PURPOSE_EXPENSE;
        }

        if ($purpose === GrossTreasuryVoucher::PURPOSE_COURIER_COD) {
            $type = GrossTreasuryVoucher::TYPE_RECEIPT;
        }

        $supplierId = old('supplier_id', $request->query('supplier_id'));
        $expenseTypeId = old('expense_type_id', $request->query('expense_type_id'));
        $salesInvoiceId = old('sales_invoice_id', $request->query('sales_invoice_id'));

        $suppliers = $this->suppliers->listForForms(200);
        $supplierIds = $suppliers->pluck('id');

        $selectedReceiptPurposeId = $receiptPurposeId !== null && $receiptPurposeId !== ''
            ? (int) $receiptPurposeId
            : ($purpose === GrossTreasuryVoucher::PURPOSE_OTHER
                ? (int) ($receiptPurposes->first()?->id ?? 0) ?: null
                : null);

        return view('gross.treasury.create', [
            'type' => $type,
            'purpose' => old('purpose', $purpose),
            'selectedSupplierId' => $supplierId !== null && $supplierId !== '' ? (int) $supplierId : null,
            'selectedExpenseTypeId' => $expenseTypeId !== null && $expenseTypeId !== '' ? (int) $expenseTypeId : null,
            'selectedReceiptPurposeId' => $selectedReceiptPurposeId,
            'selectedSalesInvoiceId' => $salesInvoiceId !== null && $salesInvoiceId !== '' ? (int) $salesInvoiceId : null,
            'accountsWithBalances' => $this->treasury->accountsWithBalances(),
            'suppliers' => $suppliers,
            'supplierSummaries' => [
                'EGP' => $this->supplierStatements->summariesForSuppliers($supplierIds, 'EGP'),
                'AED' => $this->supplierStatements->summariesForSuppliers($supplierIds, 'AED'),
            ],
            'expenseTypes' => $this->expenseTypes->activeForForms(),
            'receiptPurposes' => $receiptPurposes,
            'courierCodInvoices' => $this->salesInvoices->listCollectedWithoutTreasury(100),
        ]);
    }

    public function store(StoreGrossTreasuryVoucherRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $type = $data['type'];
        unset($data['type']);
        $files = array_values(array_filter(
            $request->file('attachments', []) ?? [],
            fn ($file) => $file !== null,
        ));

        try {
            if (($data['purpose'] ?? '') === GrossTreasuryVoucher::PURPOSE_COURIER_COD) {
                $voucher = $this->treasury->createCourierCodReceipt($request->user(), [
                    'sales_invoice_id' => (int) $data['sales_invoice_id'],
                    'account_id' => (int) $data['account_id'],
                    'voucher_date' => $data['voucher_date'],
                    'notes' => $data['notes'] ?? null,
                ], $files);
            } elseif ($type === GrossTreasuryVoucher::TYPE_PAYMENT) {
                $voucher = $this->treasury->createPayment($request->user(), $data, $files);
            } else {
                $voucher = $this->treasury->createReceipt($request->user(), $data, $files);
            }
        } catch (GrossException $e) {
            if ($e->getMessage() === 'gross.errors.too_many_treasury_attachments') {
                return back()->withInput()->withErrors([
                    'attachments' => __('gross.errors.too_many_treasury_attachments', [
                        'max' => TreasuryVoucherAttachmentService::MAX_PER_VOUCHER,
                    ]),
                ]);
            }

            return back()->withInput()->withErrors(['amount' => __($e->getMessage())]);
        }

        return redirect()
            ->route('gross.treasury.show', $voucher)
            ->with('success', __('gross.flash.voucher_created'));
    }

    public function show(GrossTreasuryVoucher $voucher): View
    {
        $this->authorize('view', $voucher);
        $voucher->load(['account', 'supplier', 'expenseType', 'receiptPurpose', 'salesInvoice', 'shippingCompany', 'creator', 'attachments.uploader']);

        return view('gross.treasury.show', compact('voucher'));
    }

    public function downloadAttachment(
        GrossTreasuryVoucher $voucher,
        GrossTreasuryVoucherAttachment $attachment,
    ): StreamedResponse {
        abort_unless($attachment->voucher_id === $voucher->id, 404);
        $this->authorize('view', $voucher);

        return $this->attachments->download($attachment);
    }
}
