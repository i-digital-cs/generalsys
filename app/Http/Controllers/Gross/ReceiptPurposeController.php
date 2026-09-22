<?php

namespace App\Http\Controllers\Gross;

use App\Exceptions\GrossException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gross\StoreGrossReceiptPurposeRequest;
use App\Http\Requests\Gross\UpdateGrossReceiptPurposeRequest;
use App\Models\Gross\GrossReceiptPurpose;
use App\Services\Gross\ReceiptPurposeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReceiptPurposeController extends Controller
{
    public function __construct(private readonly ReceiptPurposeService $receiptPurposes) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', GrossReceiptPurpose::class);

        $q = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', ''));
        $filters = ['q' => $q, 'status' => $status];

        return view('gross.receipt-purposes.index', [
            'purposes' => $this->receiptPurposes->paginate($filters),
            'filters' => $filters,
            'summary' => $this->receiptPurposes->summary(),
        ]);
    }

    public function store(StoreGrossReceiptPurposeRequest $request): RedirectResponse
    {
        $this->receiptPurposes->create($request->validated());

        return redirect()
            ->route('gross.receipt-purposes.index')
            ->with('success', __('gross.flash.receipt_purpose_created'));
    }

    public function update(UpdateGrossReceiptPurposeRequest $request, GrossReceiptPurpose $receiptPurpose): RedirectResponse
    {
        $this->receiptPurposes->update($receiptPurpose, $request->validated());

        return redirect()
            ->route('gross.receipt-purposes.index', array_filter([
                'q' => $request->query('q'),
                'status' => $request->query('status'),
            ]))
            ->with('success', __('gross.flash.receipt_purpose_updated'));
    }

    public function destroy(GrossReceiptPurpose $receiptPurpose): RedirectResponse
    {
        $this->authorize('delete', $receiptPurpose);

        try {
            $this->receiptPurposes->delete($receiptPurpose);
        } catch (GrossException $e) {
            return back()->withErrors(['receipt_purpose' => __($e->getMessage())]);
        }

        return redirect()
            ->route('gross.receipt-purposes.index')
            ->with('success', __('gross.flash.receipt_purpose_deleted'));
    }
}
