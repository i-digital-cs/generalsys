<?php

namespace App\Http\Controllers\Gross;

use App\Exceptions\GrossException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gross\GrossAllocationQtyRequest;
use App\Http\Requests\Gross\RefuseGrossSalesInvoiceRequest;
use App\Http\Requests\Gross\StoreGrossSalesInvoiceRequest;
use App\Http\Requests\Gross\UpdateGrossSalesInvoiceRequest;
use App\Models\Gross\GrossSalesInvoice;
use App\Models\Gross\GrossShipmentAllocation;
use App\Services\Gross\AllocationService;
use App\Services\Gross\CustomerService;
use App\Services\Gross\ItemService;
use App\Services\Gross\SalesInvoiceExportService;
use App\Services\Gross\SalesInvoiceService;
use App\Services\Gross\ShippingCompanyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesInvoiceController extends Controller
{
    public function __construct(
        private readonly SalesInvoiceService $invoices,
        private readonly AllocationService $allocations,
        private readonly CustomerService $customers,
        private readonly ShippingCompanyService $shipping,
        private readonly ItemService $items,
        private readonly SalesInvoiceExportService $exports,
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', GrossSalesInvoice::class);

        return view('gross.sales.index', [
            'invoices' => $this->invoices->paginate(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', GrossSalesInvoice::class);

        return view('gross.sales.create', [
            'customers' => $this->customers->listForForms(200),
            'shippingCompanies' => $this->shipping->activeList(),
            'items' => $this->items->activeForForms(500),
            'salesPeople' => $this->invoices->salesPeople(auth()->id()),
        ]);
    }

    public function store(StoreGrossSalesInvoiceRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $lines = $data['lines'];
        unset($data['lines'], $data['discount_mode']);
        foreach ($lines as $i => $line) {
            unset($lines[$i]['discount_mode']);
        }

        try {
            $invoice = $this->invoices->createDraft($request->user(), $data, $lines);
        } catch (GrossException $e) {
            return back()->withInput()->withErrors(['lines' => __($e->getMessage())]);
        }

        return redirect()
            ->route('gross.sales.show', $invoice)
            ->with('success', __('gross.flash.invoice_created'));
    }

    public function show(GrossSalesInvoice $invoice): View
    {
        $this->authorize('view', $invoice);
        $invoice->load(['lines.item', 'lines.allocation', 'customer', 'shippingCompany', 'salesUser']);

        return view('gross.sales.show', [
            'invoice' => $invoice,
        ]);
    }

    public function pdf(GrossSalesInvoice $invoice): Response
    {
        $this->authorize('view', $invoice);

        return $this->exports->downloadPdf($invoice)
            ->download('gross-invoice-'.$invoice->invoice_number.'.pdf');
    }

    public function excel(GrossSalesInvoice $invoice): StreamedResponse
    {
        $this->authorize('view', $invoice);

        return $this->exports->downloadExcel($invoice);
    }

    public function edit(GrossSalesInvoice $invoice): View
    {
        $this->authorize('update', $invoice);
        $invoice->load('lines');

        return view('gross.sales.edit', [
            'invoice' => $invoice,
            'customers' => $this->customers->listForForms(200),
            'shippingCompanies' => $this->shipping->activeList(),
            'items' => $this->items->activeForForms(500),
            'salesPeople' => $this->invoices->salesPeople($invoice->sales_user_id),
        ]);
    }

    public function update(UpdateGrossSalesInvoiceRequest $request, GrossSalesInvoice $invoice): RedirectResponse
    {
        $data = $request->validated();
        $lines = $data['lines'];
        unset($data['lines'], $data['discount_mode']);
        foreach ($lines as $i => $line) {
            unset($lines[$i]['discount_mode']);
        }

        try {
            $this->invoices->updateDraft($invoice, $data, $lines);
        } catch (GrossException $e) {
            return back()->withInput()->withErrors(['lines' => __($e->getMessage())]);
        }

        return redirect()
            ->route('gross.sales.show', $invoice)
            ->with('success', __('gross.flash.invoice_updated'));
    }

    public function confirm(GrossSalesInvoice $invoice): RedirectResponse
    {
        $this->authorize('confirm', $invoice);

        try {
            $this->invoices->confirm($invoice, request()->user());
        } catch (GrossException $e) {
            return back()->withErrors(['status' => __($e->getMessage())]);
        }

        return redirect()
            ->route('gross.sales.show', $invoice)
            ->with('success', __('gross.flash.invoice_confirmed'));
    }

    public function cancel(GrossSalesInvoice $invoice): RedirectResponse
    {
        $this->authorize('cancel', $invoice);

        try {
            $this->invoices->cancel($invoice);
        } catch (GrossException $e) {
            return back()->withErrors(['status' => __($e->getMessage())]);
        }

        return redirect()
            ->route('gross.sales.show', $invoice)
            ->with('success', __('gross.flash.invoice_cancelled'));
    }

    public function refuse(RefuseGrossSalesInvoiceRequest $request, GrossSalesInvoice $invoice): RedirectResponse
    {
        try {
            $this->invoices->refuse($invoice, $request->user(), $request->validated()['refuse_reason'] ?? null);
        } catch (GrossException $e) {
            return back()->withErrors(['status' => __($e->getMessage())]);
        }

        return redirect()
            ->route('gross.sales.show', $invoice)
            ->with('success', __('gross.flash.invoice_refused'));
    }

    public function returnLine(GrossAllocationQtyRequest $request, GrossSalesInvoice $invoice): RedirectResponse
    {
        $data = $request->validated();
        $allocation = $this->allocationForInvoice($invoice, (int) $data['allocation_id']);

        try {
            $this->allocations->partialReturn($allocation, (int) $data['qty_pieces'], $request->user());
        } catch (GrossException $e) {
            return back()->withErrors(['qty_pieces' => __($e->getMessage())]);
        }

        return redirect()
            ->route('gross.sales.show', $invoice)
            ->with('success', __('gross.flash.return_applied'));
    }

    public function lossLine(GrossAllocationQtyRequest $request, GrossSalesInvoice $invoice): RedirectResponse
    {
        $data = $request->validated();
        $allocation = $this->allocationForInvoice($invoice, (int) $data['allocation_id']);

        try {
            $this->allocations->confirmLoss($allocation, (int) $data['qty_pieces'], $request->user());
        } catch (GrossException $e) {
            return back()->withErrors(['qty_pieces' => __($e->getMessage())]);
        }

        return redirect()
            ->route('gross.sales.show', $invoice)
            ->with('success', __('gross.flash.loss_confirmed'));
    }

    private function allocationForInvoice(GrossSalesInvoice $invoice, int $allocationId): GrossShipmentAllocation
    {
        $allocation = GrossShipmentAllocation::query()
            ->whereKey($allocationId)
            ->whereHas('line', fn ($q) => $q->where('sales_invoice_id', $invoice->id))
            ->firstOrFail();

        return $allocation;
    }
}
