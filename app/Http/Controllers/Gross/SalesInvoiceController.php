<?php

namespace App\Http\Controllers\Gross;

use App\Exceptions\GrossException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gross\CollectCodRequest;
use App\Http\Requests\Gross\GrossAllocationQtyRequest;
use App\Http\Requests\Gross\ImportGrossSalesInvoiceRequest;
use App\Http\Requests\Gross\RefuseGrossSalesInvoiceRequest;
use App\Http\Requests\Gross\StoreGrossSalesInvoiceRequest;
use App\Http\Requests\Gross\UpdateGrossSalesInvoiceRequest;
use App\Models\Gross\GrossSalesInvoice;
use App\Models\Gross\GrossShipmentAllocation;
use App\Services\Gross\AllocationService;
use App\Services\Gross\CustomerService;
use App\Services\Gross\ItemService;
use App\Services\Gross\SalesInvoiceDisplayService;
use App\Services\Gross\SalesInvoiceExportService;
use App\Services\Gross\SalesInvoiceImportService;
use App\Services\Gross\SalesInvoiceService;
use App\Services\Gross\ShippingCompanyService;
use App\Services\Gross\TreasuryService;
use App\Support\GrossAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesInvoiceController extends Controller
{
    public function __construct(
        private readonly SalesInvoiceService $invoices,
        private readonly SalesInvoiceImportService $imports,
        private readonly AllocationService $allocations,
        private readonly CustomerService $customers,
        private readonly ShippingCompanyService $shipping,
        private readonly ItemService $items,
        private readonly SalesInvoiceExportService $exports,
        private readonly SalesInvoiceDisplayService $display,
        private readonly TreasuryService $treasury,
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', GrossSalesInvoice::class);

        return view('gross.sales.index', [
            'invoices' => $this->invoices->paginate(),
        ]);
    }

    public function template(): BinaryFileResponse|StreamedResponse
    {
        $this->authorize('create', GrossSalesInvoice::class);

        $path = storage_path('app/tmp/gross-sales-invoices-template.xlsx');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        $this->imports->writeTemplate($path);

        return response()->download(
            $path,
            'gross-sales-invoices-template.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        )->deleteFileAfterSend(true);
    }

    public function import(ImportGrossSalesInvoiceRequest $request): RedirectResponse
    {
        $path = $request->file('file')->getRealPath();
        $result = $this->imports->importFromPath((string) $path, $request->user());

        $message = __('gross.flash.sales_imported', [
            'created' => $result['created'],
            'skipped' => $result['skipped'],
        ]);

        $redirect = redirect()->route('gross.sales.index');

        if ($result['errors'] !== []) {
            return $redirect
                ->with('warning', $message)
                ->withErrors(['import' => $result['errors']]);
        }

        return $redirect->with('success', $message);
    }

    public function create(): View
    {
        $this->authorize('create', GrossSalesInvoice::class);

        return view('gross.sales.create', [
            'customers' => $this->customers->listForForms(200),
            'shippingCompanies' => $this->shipping->activeList(),
            'itemsById' => $this->items->findForForms($this->lineItemIdsFromRequest(null)),
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
        $invoice->load(['lines.item', 'lines.allocation', 'customer', 'shippingCompany', 'salesUser', 'courierCodTreasuryVoucher']);

        return view('gross.sales.show', [
            'invoice' => $invoice,
            'display' => $this->display->build($invoice),
            'treasuryAccounts' => GrossAccess::mayPostTreasury(auth()->user())
                ? $this->treasury->activeAccounts()
                : collect(),
            'canPostTreasury' => GrossAccess::mayPostTreasury(auth()->user()),
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
            'itemsById' => $this->items->findForForms($this->lineItemIdsFromRequest($invoice)),
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

    public function collections(Request $request): View
    {
        $this->authorize('viewAny', GrossSalesInvoice::class);

        $filters = [
            'shipping_company_id' => $request->query('shipping_company_id'),
        ];

        return view('gross.sales.collections', [
            'invoices' => $this->invoices->paginatePendingCollections($filters),
            'shippingCompanies' => $this->shipping->activeList(),
            'filters' => $filters,
            'treasuryAccounts' => GrossAccess::mayPostTreasury($request->user())
                ? $this->treasury->activeAccounts()
                : collect(),
            'canPostTreasury' => GrossAccess::mayPostTreasury($request->user()),
        ]);
    }

    public function collectCod(CollectCodRequest $request, GrossSalesInvoice $invoice): RedirectResponse
    {
        $this->authorize('collectCod', $invoice);

        $options = $request->treasuryOptions();

        try {
            $collected = $this->invoices->collectCod($invoice, $request->user(), $options);
        } catch (GrossException $e) {
            return back()->withErrors(['status' => __($e->getMessage())]);
        }

        $flash = $options['post_to_treasury'] && $collected->courierCodTreasuryVoucher
            ? 'gross.flash.cod_collected_treasury'
            : 'gross.flash.cod_collected';

        return redirect()
            ->back()
            ->with('success', __($flash));
    }

    public function markDelivered(GrossSalesInvoice $invoice): RedirectResponse
    {
        $this->authorize('markDelivered', $invoice);

        try {
            $this->invoices->markDelivered($invoice, auth()->user());
        } catch (GrossException $e) {
            return back()->withErrors(['status' => __($e->getMessage())]);
        }

        return redirect()
            ->route('gross.sales.show', $invoice)
            ->with('success', __('gross.flash.invoice_delivered'));
    }

    public function receiveRefused(GrossSalesInvoice $invoice): RedirectResponse
    {
        $this->authorize('receiveRefused', $invoice);

        try {
            $this->invoices->receiveRefusedFromCourier($invoice, auth()->user());
        } catch (GrossException $e) {
            return back()->withErrors(['status' => __($e->getMessage())]);
        }

        return redirect()
            ->route('gross.sales.show', $invoice)
            ->with('success', __('gross.flash.refused_pieces_received'));
    }

    private function allocationForInvoice(GrossSalesInvoice $invoice, int $allocationId): GrossShipmentAllocation
    {
        $allocation = GrossShipmentAllocation::query()
            ->whereKey($allocationId)
            ->whereHas('line', fn ($q) => $q->where('sales_invoice_id', $invoice->id))
            ->firstOrFail();

        return $allocation;
    }

    /**
     * @return list<int>
     */
    private function lineItemIdsFromRequest(?GrossSalesInvoice $invoice): array
    {
        $lines = old('lines');
        if (! is_array($lines)) {
            $lines = $invoice?->lines?->map(fn ($line) => ['item_id' => $line->item_id])->all() ?? [];
        }

        return collect($lines)->pluck('item_id')->filter()->map(fn ($id) => (int) $id)->all();
    }
}
