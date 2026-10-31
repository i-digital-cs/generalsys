<?php

namespace App\Http\Controllers\GeneralHouse;

use App\Http\Controllers\Controller;
use App\Http\Requests\GeneralHouse\UpdateGhInvoiceDraftRequest;
use App\Models\GeneralHouse\GhInvoice;
use App\Models\GeneralHouse\GhOrder;
use App\Models\GeneralHouse\GhServiceCategory;
use App\Services\GeneralHouse\Catalog\GhPricingResolver;
use App\Services\GeneralHouse\Invoices\GhInvoiceIssuanceService;
use App\Services\GeneralHouse\Invoices\GhInvoiceService;
use App\Services\GeneralHouse\Payments\GhPaymentBalanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly GhInvoiceService $invoices,
        private readonly GhInvoiceIssuanceService $issuance,
        private readonly GhPricingResolver $pricing,
        private readonly GhPaymentBalanceService $paymentBalances,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', GhInvoice::class);

        $rows = GhInvoice::query()
            ->with(['order'])
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->when($request->string('q')->toString(), function ($q, $term) {
                $q->where(function ($inner) use ($term) {
                    $inner->where('number', 'like', "%{$term}%")
                        ->orWhere('order_reference', 'like', "%{$term}%")
                        ->orWhere('customer_name_en', 'like', "%{$term}%")
                        ->orWhere('customer_name_ar', 'like', "%{$term}%");
                });
            })
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('gh.invoices.index', compact('rows'));
    }

    public function createFromOrder(GhOrder $order): RedirectResponse
    {
        $this->authorize('create', GhInvoice::class);

        $existing = $this->invoices->findPrimaryForOrder($order);
        if ($existing !== null) {
            return redirect()->route('gh.invoices.builder', $existing);
        }

        $invoice = $this->invoices->createDraftFromOrder($order, request()->user());

        return redirect()
            ->route('gh.invoices.builder', $invoice)
            ->with('status', __('gh.invoices.draft_created'));
    }

    public function builder(GhInvoice $invoice): View
    {
        $this->authorize('view', $invoice);
        $invoice->load(['lines', 'adjustments', 'order.customer', 'order.zone', 'minOrderOverride']);
        $order = $invoice->order;
        $catalog = [];
        $categories = collect();

        if ($invoice->isDraft() && $order?->customer) {
            $offerings = $this->pricing->availableOfferingsForCustomer($order->customer, $order->pickup_starts_at);
            $categories = GhServiceCategory::query()->where('status', 'ACTIVE')->orderBy('sort_order')->get();
            $catalog = $offerings->map(function (array $row) {
                $o = $row['offering'];

                return [
                    'offering_id' => $o->id,
                    'unit_price' => $row['unit_price'],
                    'source' => $row['source'],
                    'tax_class' => $o->tax_class,
                    'category_id' => $o->product?->category_id,
                    'product_name' => app()->getLocale() === 'ar' ? $o->product?->name_ar : ($o->product?->name_en ?: $o->product?->name_ar),
                    'treatment_name' => app()->getLocale() === 'ar' ? $o->treatment?->name_ar : ($o->treatment?->name_en ?: $o->treatment?->name_ar),
                    'image_url' => $o->product ? route('gh.products.image', $o->product) : null,
                ];
            })->values();
        }

        return view('gh.invoices.builder', [
            'invoice' => $invoice,
            'order' => $order,
            'catalog' => $catalog,
            'categories' => $categories,
        ]);
    }

    public function show(GhInvoice $invoice): View
    {
        $this->authorize('view', $invoice);
        $invoice->load(['lines', 'adjustments', 'order', 'issuer', 'payments.collector', 'payments.recorder']);
        $balance = $this->paymentBalances->forInvoice($invoice);

        return view('gh.invoices.show', compact('invoice', 'balance'));
    }

    public function print(GhInvoice $invoice): View
    {
        $this->authorize('view', $invoice);
        $invoice->load(['lines', 'adjustments', 'order', 'issuer']);

        return view('gh.invoices.print', compact('invoice'));
    }

    public function update(UpdateGhInvoiceDraftRequest $request, GhInvoice $invoice): RedirectResponse
    {
        $this->authorize('update', $invoice);
        $updated = $this->invoices->updateDraft($invoice, $request->user(), $request->validated());

        return redirect()
            ->route('gh.invoices.builder', $updated)
            ->with('status', __('gh.invoices.draft_saved'));
    }

    public function issue(Request $request, GhInvoice $invoice): RedirectResponse
    {
        $this->authorize('issue', $invoice);
        $issued = $this->issuance->issue(
            $invoice,
            $request->user(),
            $request->integer('draft_version') ?: null
        );

        return redirect()
            ->route('gh.invoices.show', $issued)
            ->with('status', __('gh.invoices.issued'));
    }
}
