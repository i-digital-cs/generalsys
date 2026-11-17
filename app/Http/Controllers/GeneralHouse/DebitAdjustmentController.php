<?php

namespace App\Http\Controllers\GeneralHouse;

use App\Http\Controllers\Controller;
use App\Models\GeneralHouse\GhDebitAdjustment;
use App\Models\GeneralHouse\GhInvoice;
use App\Models\GeneralHouse\GhProductOffering;
use App\Services\GeneralHouse\Corrections\GhDebitAdjustmentService;
use App\Services\GeneralHouse\Payments\GhPaymentBalanceService;
use App\Support\GeneralHouse\GhCatalogStatus;
use App\Support\GeneralHouse\GhDebitAdjustmentLineType;
use App\Support\GeneralHouse\GhDebitAdjustmentReason;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DebitAdjustmentController extends Controller
{
    public function __construct(
        private readonly GhDebitAdjustmentService $adjustments,
        private readonly GhPaymentBalanceService $balances,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', GhDebitAdjustment::class);

        $status = trim($request->string('status')->toString());
        $rows = GhDebitAdjustment::query()
            ->with(['invoice'])
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('gh.debit-adjustments.index', compact('rows'));
    }

    public function create(GhInvoice $invoice): View
    {
        $this->authorize('create', GhDebitAdjustment::class);
        abort_unless($invoice->isIssued(), 404);

        $invoice->load(['lines', 'order', 'payments', 'creditNotes', 'debitAdjustments']);
        $balance = $this->balances->forInvoice($invoice);
        $offerings = GhProductOffering::query()
            ->with(['product', 'treatment'])
            ->where('status', GhCatalogStatus::ACTIVE)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->limit(200)
            ->get();

        return view('gh.debit-adjustments.create', [
            'invoice' => $invoice,
            'balance' => $balance,
            'reasons' => GhDebitAdjustmentReason::ALL,
            'lineTypes' => GhDebitAdjustmentLineType::ALL,
            'offerings' => $offerings,
        ]);
    }

    public function store(Request $request, GhInvoice $invoice): RedirectResponse
    {
        $this->authorize('create', GhDebitAdjustment::class);

        $data = $request->validate([
            'reason_code' => ['required', 'string', 'max:40'],
            'reason_text' => ['required', 'string', 'max:2000'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
            'issue_now' => ['nullable', 'boolean'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.line_type' => ['required', 'string', 'max:32'],
            'lines.*.quantity' => ['required', 'integer', 'min:1'],
            'lines.*.unit_price' => ['required', 'numeric', 'gt:0'],
            'lines.*.offering_id' => ['nullable', 'integer'],
            'lines.*.invoice_line_id' => ['nullable', 'integer'],
        ]);

        $data['lines'] = array_values($data['lines']);
        $issueNow = (bool) ($data['issue_now'] ?? true);
        unset($data['issue_now']);

        $adj = $issueNow
            ? $this->adjustments->createAndIssue($invoice, $request->user(), $data)
            : $this->adjustments->createDraft($invoice, $request->user(), $data);

        return redirect()
            ->route('gh.debit-adjustments.show', $adj)
            ->with('success', $adj->isIssued()
                ? __('gh.flash.debit_adjustment_issued', ['number' => $adj->number])
                : __('gh.flash.debit_adjustment_created'));
    }

    public function show(GhDebitAdjustment $debitAdjustment): View
    {
        $this->authorize('view', $debitAdjustment);
        $debitAdjustment->load(['lines', 'events.actor', 'invoice.lines', 'creator', 'issuer']);
        $balance = $this->balances->forInvoice($debitAdjustment->invoice);

        return view('gh.debit-adjustments.show', [
            'adjustment' => $debitAdjustment,
            'balance' => $balance,
        ]);
    }

    public function issue(Request $request, GhDebitAdjustment $debitAdjustment): RedirectResponse
    {
        $this->authorize('issue', $debitAdjustment);
        $adj = $this->adjustments->issue($debitAdjustment, $request->user());

        return redirect()
            ->route('gh.debit-adjustments.show', $adj)
            ->with('success', __('gh.flash.debit_adjustment_issued', ['number' => $adj->number]));
    }

    public function cancel(Request $request, GhDebitAdjustment $debitAdjustment): RedirectResponse
    {
        $this->authorize('cancel', $debitAdjustment);
        $this->adjustments->cancel($debitAdjustment, $request->user());

        return back()->with('success', __('gh.flash.debit_adjustment_cancelled'));
    }
}
