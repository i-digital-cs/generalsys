<?php

namespace App\Http\Controllers\GeneralHouse;

use App\Http\Controllers\Controller;
use App\Http\Requests\GeneralHouse\GhReportFilterRequest;
use App\Models\GeneralHouse\GhCustomer;
use App\Models\GeneralHouse\GhZone;
use App\Models\User;
use App\Services\GeneralHouse\Reporting\GhCsvExportService;
use App\Services\GeneralHouse\Reporting\GhReportQueryService;
use App\Support\GeneralHouse\GhCatalogAccess;
use App\Support\GeneralHouse\GhReportFilters;
use App\Support\GeneralHouse\GhReportPeriod;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        private readonly GhReportQueryService $reports,
        private readonly GhCsvExportService $csv,
    ) {}

    public function index(Request $request): View
    {
        abort_unless(GhCatalogAccess::mayManage($request->user()), 403);

        return view('gh.reports.index');
    }

    public function invoices(GhReportFilterRequest $request): View|StreamedResponse
    {
        $filters = $this->filters($request);
        if ($request->boolean('export')) {
            $rows = $this->reports->invoicesForExport($filters);

            return $this->csv->download('gh_invoices', [
                'Invoice', 'Order', 'Customer', 'Issued at (UTC)', 'Total', 'Confirmed', 'Remaining', 'Payment status',
            ], $rows->map(fn ($r) => [
                $r['number'], $r['order_reference'], $r['customer'],
                (string) $r['issued_at'], $r['total_payable'], $r['confirmed'], $r['remaining'], $r['payment_status'],
            ]));
        }

        return view('gh.reports.invoices', $this->shell($filters, [
            'rows' => $this->reports->invoices($filters),
            'reports' => $this->reports,
        ]));
    }

    public function collections(GhReportFilterRequest $request): View|StreamedResponse
    {
        $filters = $this->filters($request);
        if ($request->boolean('export')) {
            $rows = $this->reports->collectionsForExport($filters);

            return $this->csv->download('gh_collections', [
                'Payment', 'Invoice', 'Customer', 'Method', 'Amount', 'Collector', 'Confirmed at (UTC)',
            ], $rows->map(fn ($p) => [
                $p->reference,
                $p->invoice?->number,
                $p->invoice?->customer_name_en ?: $p->invoice?->customer_name_ar,
                $p->method,
                $p->amount,
                $p->collector?->name_en ?: $p->collector?->name_ar,
                (string) $p->confirmed_at,
            ]));
        }

        return view('gh.reports.collections', $this->shell($filters, [
            'rows' => $this->reports->collections($filters),
        ]));
    }

    public function outstanding(GhReportFilterRequest $request): View|StreamedResponse
    {
        $filters = $this->filters($request);
        if ($request->boolean('export')) {
            $rows = $this->reports->outstandingForExport($filters);

            return $this->csv->download('gh_outstanding', [
                'Customer', 'Invoice', 'Original', 'Confirmed', 'Remaining', 'Days outstanding', 'Status',
            ], $rows->map(fn ($r) => [
                $r['customer'], $r['number'], $r['total_payable'], $r['confirmed'],
                $r['remaining'], $r['days_outstanding'], $r['payment_status'],
            ]));
        }

        return view('gh.reports.outstanding', $this->shell($filters, [
            'rows' => $this->reports->outstanding($filters),
            'reports' => $this->reports,
        ]));
    }

    public function b2b(GhReportFilterRequest $request): View|StreamedResponse
    {
        $filters = $this->filters($request);
        if ($request->boolean('export')) {
            $rows = $this->reports->b2bReceivablesForExport($filters);

            return $this->csv->download('gh_b2b_receivables', [
                'Customer', 'Contract', 'Issued total', 'Confirmed', 'Outstanding',
            ], $rows->map(fn ($r) => [
                $r->customer_name_en ?: $r->customer_name_ar,
                $r->contract_reference,
                $r->issued_total,
                $r->confirmed_total,
                $r->outstanding_total,
            ]));
        }

        return view('gh.reports.b2b', $this->shell($filters, [
            'rows' => $this->reports->b2bReceivables($filters),
        ]));
    }

    public function orders(GhReportFilterRequest $request): View|StreamedResponse
    {
        $filters = $this->filters($request);
        if ($request->boolean('export')) {
            $rows = $this->reports->ordersForExport($filters);

            return $this->csv->download('gh_orders', [
                'Reference', 'Customer', 'Type', 'Zone', 'Status', 'Pickup start (UTC)', 'Pickup driver', 'Delivery driver', 'Created (UTC)',
            ], $rows->map(fn ($o) => [
                $o->reference,
                $o->customer?->name_en ?: $o->customer?->name_ar,
                $o->customer?->type,
                $o->zone?->name_en ?: $o->zone?->name_ar,
                $o->status,
                (string) $o->pickup_starts_at,
                $o->pickupDriver?->name_en ?: $o->pickupDriver?->name_ar,
                $o->deliveryDriver?->name_en ?: $o->deliveryDriver?->name_ar,
                (string) $o->created_at,
            ]));
        }

        return view('gh.reports.orders', $this->shell($filters, [
            'rows' => $this->reports->orders($filters),
        ]));
    }

    public function pickupPerformance(GhReportFilterRequest $request): View
    {
        $filters = $this->filters($request);

        return view('gh.reports.pickup-performance', $this->shell($filters, [
            'metrics' => $this->reports->pickupPerformance($filters),
        ]));
    }

    public function deliveryPerformance(GhReportFilterRequest $request): View
    {
        $filters = $this->filters($request);

        return view('gh.reports.delivery-performance', $this->shell($filters, [
            'metrics' => $this->reports->deliveryPerformance($filters),
        ]));
    }

    private function filters(GhReportFilterRequest $request): GhReportFilters
    {
        abort_unless(GhCatalogAccess::mayManage($request->user()), 403);

        return GhReportFilters::fromValidated(array_merge(
            ['preset' => GhReportPeriod::PRESET_LAST_30],
            $request->validated(),
        ));
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function shell(GhReportFilters $filters, array $extra = []): array
    {
        return array_merge([
            'filters' => $filters,
            'query' => array_filter($filters->toQuery(), fn ($v) => $v !== null && $v !== ''),
            'customers' => GhCustomer::query()->orderBy('name_en')->limit(500)->get(['id', 'name_ar', 'name_en', 'type']),
            'zones' => GhZone::query()->orderBy('sort_order')->orderBy('name_en')->get(['id', 'name_ar', 'name_en']),
            'driverOptions' => User::query()->where('role', 'DRIVER')->where('status', 'ACTIVE')
                ->orderBy('name_en')->get(['id', 'name_ar', 'name_en']),
            'displayTz' => $filters->period->timezone,
        ], $extra);
    }
}
