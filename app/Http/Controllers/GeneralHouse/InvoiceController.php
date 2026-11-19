<?php

namespace App\Http\Controllers\GeneralHouse;

use App\Http\Controllers\Controller;
use App\Http\Requests\GeneralHouse\GhInvoiceIndexRequest;
use App\Http\Requests\GeneralHouse\UpdateGhInvoiceDraftRequest;
use App\Models\GeneralHouse\GhInvoice;
use App\Models\GeneralHouse\GhOrder;
use App\Models\GeneralHouse\GhServiceCategory;
use App\Services\GeneralHouse\Catalog\GhPricingResolver;
use App\Services\GeneralHouse\Invoices\GhInvoiceIssuanceService;
use App\Services\GeneralHouse\Invoices\GhInvoiceListService;
use App\Services\GeneralHouse\Invoices\GhInvoiceService;
use App\Services\GeneralHouse\Payments\GhDriverCashDashboardService;
use App\Services\GeneralHouse\Payments\GhOrderFinancialSummaryService;
use App\Services\GeneralHouse\Payments\GhPaymentBalanceService;
use App\Services\GeneralHouse\Settings\GhVatConfigService;
use App\Support\GeneralHouse\GhMoney;
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
        private readonly GhVatConfigService $vatConfig,
        private readonly GhInvoiceListService $invoiceList,
        private readonly GhDriverCashDashboardService $custodyDashboard,
        private readonly GhOrderFinancialSummaryService $financialSummary,
    ) {}

    public function index(GhInvoiceIndexRequest $request): View
    {
        $this->authorize('viewAny', GhInvoice::class);

        return view('gh.invoices.index', [
            'rows' => $this->invoiceList->paginate($request->validated()),
            'filters' => $request->validated(),
            'driverOptions' => $this->custodyDashboard->driverOptions(),
            'displayTz' => (string) config('general_house.timezone', 'Asia/Dubai'),
        ]);
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
        $catalog = collect();
        $categories = collect();
        $catalogMeta = [
            'mode' => 'B2C',
            'groups' => [],
            'ooc_groups' => [],
            'contract_reference' => null,
            'message' => null,
        ];
        if ($invoice->isDraft() && $order?->customer) {
            $categories = GhServiceCategory::query()->where('status', 'ACTIVE')->orderBy('sort_order')->get();
            $catalogMeta = $this->pricing->invoiceBuilderCatalog($order->customer, $order->pickup_starts_at);
            // Flat catalog kept for legacy JS consumers / tests.
            $catalog = collect($catalogMeta['groups'])->flatMap(function (array $group) {
                return collect($group['treatments'] ?? [])->map(fn (array $t) => [
                    'offering_id' => $t['offering_id'],
                    'unit_price' => $t['unit_price'],
                    'source' => $t['source'],
                    'tax_class' => $t['tax_class'] ?? null,
                    'category_id' => $group['category_id'] ?? null,
                    'product_code' => $group['code'] ?? null,
                    'product_name' => app()->getLocale() === 'ar' ? $group['name_ar'] : ($group['name_en'] ?: $group['name_ar']),
                    'treatment_name' => app()->getLocale() === 'ar' ? $t['name_ar'] : ($t['name_en'] ?: $t['name_ar']),
                    'image_url' => $group['image_url'] ?? null,
                    'out_of_contract' => (bool) ($group['out_of_contract'] ?? false),
                ]);
            })->values();
        }

        $zoneMinimum = GhMoney::of((string) ($invoice->min_order_original_amount ?? '0.00'));
        $effectiveMinimum = GhMoney::of((string) ($invoice->min_order_effective_amount ?? $zoneMinimum));
        $afterDiscounts = GhMoney::of((string) ($invoice->lines_after_discounts ?? '0.00'));
        $minChanged = GhMoney::cmp($effectiveMinimum, $zoneMinimum) !== 0;
        $belowMinimum = $invoice->lines->isNotEmpty()
            && GhMoney::cmp($effectiveMinimum, '0') > 0
            && GhMoney::cmp($afterDiscounts, $effectiveMinimum) < 0;
        $minShortfall = $belowMinimum
            ? GhMoney::roundHalfUp(GhMoney::sub($effectiveMinimum, $afterDiscounts))
            : '0.00';
        $canEditMinimum = request()->user()?->isManager() === true;

        return view('gh.invoices.builder', [
            'invoice' => $invoice,
            'order' => $order,
            'catalog' => $catalog,
            'catalogMeta' => $catalogMeta,
            'categories' => $categories,
            'belowMinimum' => $belowMinimum,
            'minShortfall' => $minShortfall,
            'zoneMinimum' => $zoneMinimum,
            'effectiveMinimum' => $effectiveMinimum,
            'minChanged' => $minChanged,
            'canEditMinimum' => $canEditMinimum,
            'standardVatRate' => (string) ($invoice->standard_vat_rate ?: $this->vatConfig->standardRate()),
        ]);
    }

    public function show(GhInvoice $invoice): View
    {
        $this->authorize('view', $invoice);
        $invoice->load([
            'lines',
            'adjustments',
            'order',
            'issuer',
            'payments.collector',
            'payments.recorder',
            'creditNotes',
            'debitAdjustments',
        ]);
        $balance = $this->paymentBalances->forInvoice($invoice);
        $financial = $this->financialSummary->forInvoice($invoice);
        $displayTz = (string) config('general_house.timezone', 'Asia/Dubai');

        return view('gh.invoices.show', compact('invoice', 'balance', 'financial', 'displayTz'));
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
