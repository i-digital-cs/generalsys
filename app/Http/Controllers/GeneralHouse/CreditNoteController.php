<?php

namespace App\Http\Controllers\GeneralHouse;

use App\Http\Controllers\Controller;
use App\Models\GeneralHouse\GhCreditNote;
use App\Models\GeneralHouse\GhInvoice;
use App\Services\GeneralHouse\Corrections\GhCreditNoteService;
use App\Services\GeneralHouse\Payments\GhPaymentBalanceService;
use App\Support\GeneralHouse\GhCreditNoteReason;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CreditNoteController extends Controller
{
    public function __construct(
        private readonly GhCreditNoteService $creditNotes,
        private readonly GhPaymentBalanceService $balances,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', GhCreditNote::class);

        $rows = GhCreditNote::query()
            ->with(['invoice'])
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('gh.credit-notes.index', compact('rows'));
    }

    public function create(GhInvoice $invoice): View
    {
        $this->authorize('create', GhCreditNote::class);
        $invoice->load('lines');
        $balance = $this->balances->forInvoice($invoice);

        return view('gh.credit-notes.create', [
            'invoice' => $invoice,
            'balance' => $balance,
            'reasons' => GhCreditNoteReason::ALL,
        ]);
    }

    public function store(Request $request, GhInvoice $invoice): RedirectResponse
    {
        $this->authorize('create', GhCreditNote::class);

        $data = $request->validate([
            'reason_code' => ['required', 'string', 'max:40'],
            'reason_text' => ['required', 'string', 'max:2000'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.invoice_line_id' => ['required', 'integer'],
            'lines.*.quantity_credited' => ['required', 'integer', 'min:0'],
        ]);
        $data['lines'] = array_values(array_filter(
            $data['lines'],
            fn (array $line) => (int) ($line['quantity_credited'] ?? 0) > 0
        ));
        if ($data['lines'] === []) {
            return back()->withErrors(['lines' => __('gh.credit_notes.lines_required')])->withInput();
        }

        $note = $this->creditNotes->createDraft($invoice, $request->user(), $data);

        return redirect()
            ->route('gh.credit-notes.show', $note)
            ->with('status', __('gh.credit_notes.created'));
    }

    public function show(GhCreditNote $creditNote): View
    {
        $this->authorize('view', $creditNote);
        $creditNote->load(['lines', 'events.actor', 'invoice.lines']);
        $balance = $this->balances->forInvoice($creditNote->invoice);

        return view('gh.credit-notes.show', [
            'creditNote' => $creditNote,
            'balance' => $balance,
        ]);
    }

    public function submit(Request $request, GhCreditNote $creditNote): RedirectResponse
    {
        $this->authorize('submit', $creditNote);
        $this->creditNotes->submitForApproval($creditNote, $request->user());

        return back()->with('status', __('gh.credit_notes.submitted'));
    }

    public function approve(Request $request, GhCreditNote $creditNote): RedirectResponse
    {
        $this->authorize('approve', $creditNote);
        $this->creditNotes->approve($creditNote, $request->user());

        return back()->with('status', __('gh.credit_notes.approved'));
    }

    public function reject(Request $request, GhCreditNote $creditNote): RedirectResponse
    {
        $this->authorize('reject', $creditNote);
        $data = $request->validate(['rejection_reason' => ['required', 'string', 'max:2000']]);
        $this->creditNotes->reject($creditNote, $request->user(), $data['rejection_reason']);

        return back()->with('status', __('gh.credit_notes.rejected'));
    }

    public function issue(Request $request, GhCreditNote $creditNote): RedirectResponse
    {
        $this->authorize('issue', $creditNote);
        $note = $this->creditNotes->issue($creditNote, $request->user());

        return redirect()
            ->route('gh.credit-notes.show', $note)
            ->with('status', __('gh.credit_notes.issued', ['number' => $note->number]));
    }

    public function cancel(Request $request, GhCreditNote $creditNote): RedirectResponse
    {
        $this->authorize('cancel', $creditNote);
        $this->creditNotes->cancel($creditNote, $request->user());

        return back()->with('status', __('gh.credit_notes.cancelled'));
    }
}
