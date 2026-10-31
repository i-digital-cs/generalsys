<?php

namespace App\Http\Controllers\GeneralHouse;

use App\Http\Controllers\Controller;
use App\Http\Requests\GeneralHouse\InitiateGhOnlinePaymentRequest;
use App\Http\Requests\GeneralHouse\RecordGhCashPaymentRequest;
use App\Models\GeneralHouse\GhInvoice;
use App\Models\GeneralHouse\GhPayment;
use App\Services\GeneralHouse\Payments\GhPaymentBalanceService;
use App\Services\GeneralHouse\Payments\GhPaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function __construct(
        private readonly GhPaymentService $payments,
        private readonly GhPaymentBalanceService $balances,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', GhPayment::class);

        $rows = GhPayment::query()
            ->with(['invoice'])
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->when($request->string('method')->toString(), fn ($q, $m) => $q->where('method', $m))
            ->when($request->string('q')->toString(), function ($q, $term) {
                $q->where(function ($inner) use ($term) {
                    $inner->where('reference', 'like', "%{$term}%")
                        ->orWhere('external_ref', 'like', "%{$term}%");
                });
            })
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('gh.payments.index', compact('rows'));
    }

    public function storeCash(RecordGhCashPaymentRequest $request, GhInvoice $invoice): RedirectResponse
    {
        $this->authorize('recordCash', [GhPayment::class, $invoice]);

        $payment = $this->payments->recordConfirmedCash($invoice, $request->user(), $request->validated());

        return redirect()
            ->route('gh.invoices.show', $invoice)
            ->with('status', __('gh.payments.cash_recorded', ['ref' => $payment->reference]));
    }

    public function storeOnline(InitiateGhOnlinePaymentRequest $request, GhInvoice $invoice): RedirectResponse
    {
        $this->authorize('initiateOnline', [GhPayment::class, $invoice]);

        $payment = $this->payments->initiateOnline($invoice, $request->user(), $request->validated());

        return redirect()
            ->route('gh.invoices.show', $invoice)
            ->with('status', __('gh.payments.online_initiated', ['ref' => $payment->reference]));
    }

    public function cancel(Request $request, GhPayment $payment): RedirectResponse
    {
        $this->authorize('cancelPending', $payment);

        $this->payments->cancelPending($payment, $request->user(), $request->string('reason')->toString() ?: null);

        return redirect()
            ->route('gh.invoices.show', $payment->invoice_id)
            ->with('status', __('gh.payments.cancelled'));
    }
}
