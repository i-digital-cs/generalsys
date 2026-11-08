<?php

namespace App\Http\Controllers\GeneralHouse;

use App\Http\Controllers\Controller;
use App\Models\GeneralHouse\GhInvoice;
use App\Models\GeneralHouse\GhRefund;
use App\Services\GeneralHouse\Corrections\GhRefundService;
use App\Services\GeneralHouse\Payments\GhPaymentBalanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RefundController extends Controller
{
    public function __construct(
        private readonly GhRefundService $refunds,
        private readonly GhPaymentBalanceService $balances,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', GhRefund::class);

        $rows = GhRefund::query()
            ->with(['invoice'])
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('gh.refunds.index', compact('rows'));
    }

    public function create(GhInvoice $invoice): View
    {
        $this->authorize('request', GhRefund::class);
        $balance = $this->balances->forInvoice($invoice);

        return view('gh.refunds.create', compact('invoice', 'balance'));
    }

    public function store(Request $request, GhInvoice $invoice): RedirectResponse
    {
        $this->authorize('request', GhRefund::class);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason_code' => ['required', 'string', 'max:40'],
            'reason_text' => ['required', 'string', 'max:2000'],
            'evidence_notes' => ['nullable', 'string', 'max:2000'],
            'credit_note_id' => ['nullable', 'integer'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
        ]);

        $refund = $this->refunds->request($invoice, $request->user(), $data);

        return redirect()
            ->route('gh.refunds.show', $refund)
            ->with('status', __('gh.refunds.requested'));
    }

    public function show(GhRefund $refund): View
    {
        $this->authorize('view', $refund);
        $refund->load(['events.actor', 'invoice', 'creditNote']);
        $balance = $this->balances->forInvoice($refund->invoice);

        return view('gh.refunds.show', compact('refund', 'balance'));
    }

    public function submit(Request $request, GhRefund $refund): RedirectResponse
    {
        $this->authorize('submit', $refund);
        $this->refunds->submitForApproval($refund, $request->user());

        return back()->with('status', __('gh.refunds.submitted'));
    }

    public function approve(Request $request, GhRefund $refund): RedirectResponse
    {
        $this->authorize('approve', $refund);
        $this->refunds->approve($refund, $request->user());

        return back()->with('status', __('gh.refunds.approved'));
    }

    public function reject(Request $request, GhRefund $refund): RedirectResponse
    {
        $this->authorize('reject', $refund);
        $data = $request->validate(['rejection_reason' => ['required', 'string', 'max:2000']]);
        $this->refunds->reject($refund, $request->user(), $data['rejection_reason']);

        return back()->with('status', __('gh.refunds.rejected'));
    }

    public function payOut(Request $request, GhRefund $refund): RedirectResponse
    {
        $this->authorize('payOut', $refund);
        $paid = $this->refunds->payOut($refund, $request->user());

        return redirect()
            ->route('gh.refunds.show', $paid)
            ->with('status', __('gh.refunds.paid_out', ['ref' => $paid->reference]));
    }

    public function cancel(Request $request, GhRefund $refund): RedirectResponse
    {
        $this->authorize('cancel', $refund);
        $this->refunds->cancel($refund, $request->user());

        return back()->with('status', __('gh.refunds.cancelled'));
    }
}
