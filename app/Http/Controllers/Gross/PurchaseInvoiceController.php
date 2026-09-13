<?php

namespace App\Http\Controllers\Gross;

use App\Exceptions\GrossException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gross\ImportGrossPurchaseInvoiceRequest;
use App\Http\Requests\Gross\StoreGrossPurchaseInvoiceRequest;
use App\Http\Requests\Gross\StoreGrossPurchaseReturnRequest;
use App\Http\Requests\Gross\UpdateGrossPurchaseInvoiceRequest;
use App\Models\Gross\GrossPurchaseInvoice;
use App\Services\Gross\ItemService;
use App\Services\Gross\PurchaseInvoiceDisplayService;
use App\Services\Gross\PurchaseInvoiceExportService;
use App\Services\Gross\PurchaseInvoiceImportService;
use App\Services\Gross\PurchaseInvoiceService;
use App\Services\Gross\PurchaseReturnService;
use App\Services\Gross\SupplierService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PurchaseInvoiceController extends Controller
{
    public function __construct(
        private readonly PurchaseInvoiceService $invoices,
        private readonly PurchaseInvoiceImportService $imports,
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

    public function template(): BinaryFileResponse|StreamedResponse
    {
        $this->authorize('create', GrossPurchaseInvoice::class);

        $path = storage_path('app/tmp/gross-purchase-invoices-template.xlsx');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        $this->imports->writeTemplate($path);

        return response()->download(
            $path,
            'gross-purchase-invoices-template.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        )->deleteFileAfterSend(true);
    }

    public function import(ImportGrossPurchaseInvoiceRequest $request): RedirectResponse
    {
        $path = $request->file('file')->getRealPath();
        $result = $this->imports->importFromPath((string) $path, $request->user());

        $message = __('gross.flash.purchase_imported', [
            'created' => $result['created'],
            'skipped' => $result['skipped'],
        ]);

        $redirect = redirect()->route('gross.purchases.index');

        if ($result['errors'] !== []) {
            return $redirect
                ->with('warning', $message)
                ->withErrors(['import' => $result['errors']]);
        }

        return $redirect->with('success', $message);
    }

    public function create(): View
    {
        $this->authorize('create', GrossPurchaseInvoice::class);

        return view('gross.purchases.create', [
            'suppliers' => $this->suppliers->listForForms(200),
            'itemsById' => $this->items->findForForms($this->lineItemIdsFromRequest(null)),
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
            'itemsById' => $this->items->findForForms($this->lineItemIdsFromRequest($purchase)),
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

    /**
     * @return list<int>
     */
    private function lineItemIdsFromRequest(?GrossPurchaseInvoice $invoice): array
    {
        $lines = old('lines');
        if (! is_array($lines)) {
            $lines = $invoice?->lines?->map(fn ($line) => ['item_id' => $line->item_id])->all() ?? [];
        }

        return collect($lines)->pluck('item_id')->filter()->map(fn ($id) => (int) $id)->all();
    }
}
