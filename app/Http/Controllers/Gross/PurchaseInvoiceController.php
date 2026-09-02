<?php

namespace App\Http\Controllers\Gross;

use App\Exceptions\GrossException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gross\StoreGrossPurchaseInvoiceRequest;
use App\Http\Requests\Gross\StoreGrossPurchaseReturnRequest;
use App\Http\Requests\Gross\UpdateGrossPurchaseInvoiceRequest;
use App\Models\Gross\GrossPurchaseInvoice;
use App\Services\Gross\ItemService;
use App\Services\Gross\PurchaseInvoiceDisplayService;
use App\Services\Gross\PurchaseInvoiceExportService;
use App\Services\Gross\PurchaseInvoiceService;
use App\Services\Gross\PurchaseReturnService;
use App\Services\Gross\SupplierService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PurchaseInvoiceController extends Controller
{
    public function __construct(
        private readonly PurchaseInvoiceService $invoices,
        private readonly PurchaseReturnService $returns,
        private readonly SupplierService $suppliers,
        private readonly ItemService $items,
        private readonly PurchaseInvoiceDisplayService $display,
        private readonly PurchaseInvoiceExportService $exports,
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', GrossPurchaseInvoice::class);

        return view('gross.purchases.index', [
            'invoices' => $this->invoices->paginate(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', GrossPurchaseInvoice::class);

        return view('gross.purchases.create', [
            'suppliers' => $this->suppliers->listForForms(200),
            'items' => $this->items->activeForForms(500),
        ]);
    }

    public function store(StoreGrossPurchaseInvoiceRequest $request): RedirectResponse
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
            ->route('gross.purchases.show', $invoice)
            ->with('success', __('gross.flash.purchase_created'));
    }

    public function show(GrossPurchaseInvoice $purchase): View
    {
        $this->authorize('view', $purchase);
        $purchase->load(['lines.item', 'supplier', 'creator', 'returns.lines', 'returns.creator']);

        return view('gross.purchases.show', [
            'invoice' => $purchase,
            'display' => $this->display->build($purchase),
        ]);
    }

    public function pdf(GrossPurchaseInvoice $purchase): Response
    {
        $this->authorize('view', $purchase);

        return $this->exports->downloadPdf($purchase)
            ->download('gross-purchase-'.$purchase->invoice_number.'.pdf');
    }

    public function excel(GrossPurchaseInvoice $purchase): StreamedResponse
    {
        $this->authorize('view', $purchase);

        return $this->exports->downloadExcel($purchase);
    }

    public function edit(GrossPurchaseInvoice $purchase): View
    {
        $this->authorize('update', $purchase);
        $purchase->load('lines');

        return view('gross.purchases.edit', [
            'invoice' => $purchase,
            'suppliers' => $this->suppliers->listForForms(200),
            'items' => $this->items->activeForForms(500),
        ]);
    }

    public function update(UpdateGrossPurchaseInvoiceRequest $request, GrossPurchaseInvoice $purchase): RedirectResponse
    {
        $data = $request->validated();
        $lines = $data['lines'];
        unset($data['lines'], $data['discount_mode']);
        foreach ($lines as $i => $line) {
            unset($lines[$i]['discount_mode']);
        }

        try {
            $this->invoices->updateDraft($purchase, $data, $lines);
        } catch (GrossException $e) {
            return back()->withInput()->withErrors(['lines' => __($e->getMessage())]);
        }

        return redirect()
            ->route('gross.purchases.show', $purchase)
            ->with('success', __('gross.flash.purchase_updated'));
    }

    public function confirm(GrossPurchaseInvoice $purchase): RedirectResponse
    {
        $this->authorize('confirm', $purchase);

        try {
            $this->invoices->confirm($purchase, request()->user());
        } catch (GrossException $e) {
            return back()->withErrors(['status' => __($e->getMessage())]);
        }

        return redirect()
            ->route('gross.purchases.show', $purchase)
            ->with('success', __('gross.flash.purchase_confirmed'));
    }

    public function cancel(GrossPurchaseInvoice $purchase): RedirectResponse
    {
        $this->authorize('cancel', $purchase);

        try {
            $this->invoices->cancel($purchase);
        } catch (GrossException $e) {
            return back()->withErrors(['status' => __($e->getMessage())]);
        }

        return redirect()
            ->route('gross.purchases.show', $purchase)
            ->with('success', __('gross.flash.purchase_cancelled'));
    }

    public function returnLines(StoreGrossPurchaseReturnRequest $request, GrossPurchaseInvoice $purchase): RedirectResponse
    {
        $data = $request->validated();

        try {
            $this->returns->create(
                $request->user(),
                $purchase,
                $data['lines'],
                $data['notes'] ?? null,
            );
        } catch (GrossException $e) {
            return back()->withInput()->withErrors(['return' => __($e->getMessage())]);
        }

        return redirect()
            ->route('gross.purchases.show', $purchase)
            ->with('success', __('gross.flash.purchase_return_applied'));
    }
}
