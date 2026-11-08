<?php

namespace App\Http\Controllers\GeneralHouse;

use App\Http\Controllers\Controller;
use App\Models\GeneralHouse\GhFinancialCorrection;
use App\Models\GeneralHouse\GhPayment;
use App\Services\GeneralHouse\Corrections\GhFinancialCorrectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinancialCorrectionController extends Controller
{
    public function __construct(
        private readonly GhFinancialCorrectionService $corrections,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', GhFinancialCorrection::class);

        $rows = GhFinancialCorrection::query()
            ->with(['invoice', 'payment'])
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('gh.financial-corrections.index', compact('rows'));
    }

    public function storeFromPayment(Request $request, GhPayment $payment): RedirectResponse
    {
        $this->authorize('request', GhFinancialCorrection::class);

        $data = $request->validate([
            'reason_text' => ['required', 'string', 'max:2000'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
        ]);

        $corr = $this->corrections->requestPaymentRecordingError($payment, $request->user(), $data);

        return redirect()
            ->route('gh.financial-corrections.show', $corr)
            ->with('status', __('gh.financial_corrections.requested'));
    }

    public function show(GhFinancialCorrection $financialCorrection): View
    {
        $this->authorize('view', $financialCorrection);
        $financialCorrection->load(['events.actor', 'invoice', 'payment']);

        return view('gh.financial-corrections.show', [
            'correction' => $financialCorrection,
        ]);
    }

    public function submit(Request $request, GhFinancialCorrection $financialCorrection): RedirectResponse
    {
        $this->authorize('submit', $financialCorrection);
        $this->corrections->submitForApproval($financialCorrection, $request->user());

        return back()->with('status', __('gh.financial_corrections.submitted'));
    }

    public function approve(Request $request, GhFinancialCorrection $financialCorrection): RedirectResponse
    {
        $this->authorize('approve', $financialCorrection);
        $this->corrections->approve($financialCorrection, $request->user());

        return back()->with('status', __('gh.financial_corrections.approved'));
    }

    public function reject(Request $request, GhFinancialCorrection $financialCorrection): RedirectResponse
    {
        $this->authorize('reject', $financialCorrection);
        $data = $request->validate(['rejection_reason' => ['required', 'string', 'max:2000']]);
        $this->corrections->reject($financialCorrection, $request->user(), $data['rejection_reason']);

        return back()->with('status', __('gh.financial_corrections.rejected'));
    }

    public function execute(Request $request, GhFinancialCorrection $financialCorrection): RedirectResponse
    {
        $this->authorize('execute', $financialCorrection);
        $this->corrections->execute($financialCorrection, $request->user());

        return back()->with('status', __('gh.financial_corrections.executed'));
    }
}
