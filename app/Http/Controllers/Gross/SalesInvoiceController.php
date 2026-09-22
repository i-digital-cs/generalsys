<?php

namespace App\Http\Controllers\Gross;

use App\Exceptions\GrossException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gross\CollectCodRequest;
use App\Http\Requests\Gross\GrossAllocationQtyRequest;
use App\Http\Requests\Gross\ImportGrossSalesInvoiceRequest;
use App\Http\Requests\Gross\ReceiveReturnedLineRequest;
use App\Http\Requests\Gross\RefuseGrossSalesInvoiceRequest;
use App\Http\Requests\Gross\StoreGrossSalesInvoiceRequest;
use App\Http\Requests\Gross\StorePackingImageRequest;
use App\Http\Requests\Gross\StoreSalesInvoiceMediaRequest;
use App\Http\Requests\Gross\UpdateGrossSalesInvoiceRequest;
use App\Models\Gross\GrossSalesInvoice;
use App\Models\Gross\GrossSalesInvoiceMedia;
use App\Models\Gross\GrossShipmentAllocation;
use App\Services\Gross\AllocationService;
use App\Services\Gross\CashierCatalogService;
use App\Services\Gross\CustomerService;
use App\Services\Gross\ItemService;
use App\Services\Gross\SalesInvoiceDisplayService;
use App\Services\Gross\SalesInvoiceExportService;
use App\Services\Gross\SalesInvoiceImportService;
use App\Services\Gross\SalesInvoiceMediaService;
use App\Services\Gross\SalesInvoiceService;
use App\Services\Gross\ShippingCompanyService;
use App\Services\Gross\TreasuryService;
use App\Support\GrossAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
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
        private readonly CashierCatalogService $cashierCatalog,
        private readonly SalesInvoiceMediaService $media,
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

    public function cashier(): View
    {
        $this->authorize('create', GrossSalesInvoice::class);

        return view('gross.sales.cashier', [
            'customers' => $this->customers->listForForms(200),
            'shippingCompanies' => $this->shipping->activeList(),
            'salesPeople' => $this->invoices->salesPeople(auth()->id()),
            'catalogUrls' => [
                'categories' => route('gross.sales.cashier.categories'),
                'subcategories' => route('gross.sales.cashier.subcategories'),
                'products' => route('gross.sales.cashier.products'),
                'variants' => route('gross.sales.cashier.variants'),
            ],
        ]);
    }

    public function cashierCategories(): JsonResponse
    {
        $this->authorize('create', GrossSalesInvoice::class);

        return response()->json($this->cashierCatalog->categories());
    }

    public function cashierSubcategories(Request $request): JsonResponse
    {
        $this->authorize('create', GrossSalesInvoice::class);

        $categoryId = (int) $request->query('category_id', 0);

        return response()->json($this->cashierCatalog->subcategories($categoryId));
    }

    public function cashierProducts(Request $request): JsonResponse
    {
        $this->authorize('create', GrossSalesInvoice::class);

        $subcategoryId = $request->filled('subcategory_id')
            ? (int) $request->query('subcategory_id')
            : null;
        $q = $request->query('q');

        return response()->json($this->cashierCatalog->productGroups(
            $subcategoryId,
            is_string($q) ? $q : null,
        ));
    }

    public function cashierVariants(Request $request): JsonResponse
    {
        $this->authorize('create', GrossSalesInvoice::class);

        $name = (string) $request->query('name', '');
        $subcategoryId = $request->filled('subcategory_id')
            ? (int) $request->query('subcategory_id')
            : null;

        return response()->json($this->cashierCatalog->variants($name, $subcategoryId));
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
        $invoice->load([
            'lines.item.images',
            'lines.allocation',
            'customer',
            'shippingCompany',
            'salesUser',
            'courierCodTreasuryVoucher',
            'media.uploader',
        ]);

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
            $this->invoices->update($invoice, $data, $lines, $request->user());
        } catch (GrossException $e) {
            return back()->withInput()->withErrors(['lines' => __($e->getMessage())]);
        }

        return redirect()
            ->route('gross.sales.show', $invoice)
            ->with('success', __('gross.flash.invoice_updated'));
    }

    public function destroy(GrossSalesInvoice $invoice): RedirectResponse
    {
        $this->authorize('delete', $invoice);

        try {
            $this->invoices->delete($invoice, request()->user());
        } catch (GrossException $e) {
            return back()->withErrors(['status' => __($e->getMessage())]);
        }

        return redirect()
            ->route('gross.sales.index')
            ->with('success', __('gross.flash.invoice_deleted'));
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

    public function fulfillmentQueue(string $queue): View
    {
        $this->authorize('viewAny', GrossSalesInvoice::class);

        $allowed = ['preparing', 'ready', 'with_courier', 'delivered', 'returned'];
        abort_unless(in_array($queue, $allowed, true), 404);

        return view('gross.sales.fulfillment', [
            'queue' => $queue,
            'invoices' => $this->invoices->paginateFulfillmentQueue($queue),
        ]);
    }

    public function storePackingImage(StorePackingImageRequest $request, GrossSalesInvoice $invoice): RedirectResponse
    {
        try {
            $this->invoices->storePackingImage($invoice, $request->file('packing_image'), $request->user());
        } catch (GrossException $e) {
            return back()->withErrors(['packing_image' => __($e->getMessage())]);
        }

        return redirect()
            ->route('gross.sales.show', $invoice)
            ->with('success', __('gross.flash.packing_image_uploaded'));
    }

    public function packingImage(GrossSalesInvoice $invoice): StreamedResponse
    {
        $this->authorize('view', $invoice);
        abort_unless($invoice->hasPackingImage(), 404);
        abort_unless(Storage::disk('local')->exists($invoice->packing_image_path), 404);

        return Storage::disk('local')->response(
            $invoice->packing_image_path,
            $invoice->packing_image_original_name ?: 'packing.jpg',
            ['Content-Type' => $invoice->packing_image_mime ?: 'image/jpeg']
        );
    }

    public function storeMedia(StoreSalesInvoiceMediaRequest $request, GrossSalesInvoice $invoice): RedirectResponse
    {
        try {
            $this->media->store(
                $invoice,
                $request->file('image'),
                $request->user(),
                $request->validated('title'),
                $request->validated('description'),
            );
        } catch (GrossException $e) {
            return back()->withInput()->withErrors(['image' => __($e->getMessage(), [
                'max' => SalesInvoiceMediaService::MAX_PER_INVOICE,
            ])]);
        }

        return redirect()
            ->route('gross.sales.show', $invoice)
            ->with('success', __('gross.flash.invoice_media_uploaded'));
    }

    public function showMedia(GrossSalesInvoice $invoice, GrossSalesInvoiceMedia $media): StreamedResponse
    {
        $this->authorize('view', $invoice);
        abort_unless((int) $media->sales_invoice_id === (int) $invoice->id, 404);

        return $this->media->response($media);
    }

    public function destroyMedia(GrossSalesInvoice $invoice, GrossSalesInvoiceMedia $media): RedirectResponse
    {
        $this->authorize('deleteMedia', $invoice);
        abort_unless((int) $media->sales_invoice_id === (int) $invoice->id, 404);

        $this->media->delete($media);

        return redirect()
            ->route('gross.sales.show', $invoice)
            ->with('success', __('gross.flash.invoice_media_deleted'));
    }

    public function markReadyInWarehouse(GrossSalesInvoice $invoice): RedirectResponse
    {
        $this->authorize('markReadyInWarehouse', $invoice);

        try {
            $this->invoices->markReadyInWarehouse($invoice);
        } catch (GrossException $e) {
            return back()->withErrors(['status' => __($e->getMessage())]);
        }

        return redirect()
            ->route('gross.sales.show', $invoice)
            ->with('success', __('gross.flash.invoice_ready_warehouse'));
    }

    public function handToCourier(GrossSalesInvoice $invoice): RedirectResponse
    {
        $this->authorize('handToCourier', $invoice);

        try {
            $this->invoices->handToCourier($invoice, request()->user());
        } catch (GrossException $e) {
            return back()->withErrors(['status' => __($e->getMessage())]);
        }

        return redirect()
            ->route('gross.sales.show', $invoice)
            ->with('success', __('gross.flash.invoice_handed_courier'));
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

    public function receiveReturnedPieces(GrossSalesInvoice $invoice): RedirectResponse
    {
        $this->authorize('receiveReturnedPieces', $invoice);

        try {
            $this->invoices->receiveReturnedPiecesFromCourier($invoice, auth()->user());
        } catch (GrossException $e) {
            return back()->withErrors(['status' => __($e->getMessage())]);
        }

        return redirect()
            ->route('gross.sales.show', $invoice)
            ->with('success', __('gross.flash.returned_pieces_received'));
    }

    public function receiveReturnedLine(ReceiveReturnedLineRequest $request, GrossSalesInvoice $invoice): RedirectResponse
    {
        $data = $request->validated();
        $allocation = $this->allocationForInvoice($invoice, (int) $data['allocation_id']);

        try {
            $this->allocations->receiveReturnedFromCourier(
                $allocation,
                $request->user(),
                (int) $data['qty_pieces'],
            );
        } catch (GrossException $e) {
            return back()->withErrors(['qty_pieces' => __($e->getMessage())]);
        }

        return redirect()
            ->route('gross.sales.show', $invoice)
            ->with('success', __('gross.flash.returned_pieces_received'));
    }

    /** @deprecated Use receiveReturnedPieces() */
    public function receiveRefused(GrossSalesInvoice $invoice): RedirectResponse
    {
        return $this->receiveReturnedPieces($invoice);
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
