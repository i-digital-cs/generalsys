<?php

namespace App\Http\Controllers\GeneralHouse;

use App\Http\Controllers\Controller;
use App\Http\Requests\GeneralHouse\GhDriverCashFilterRequest;
use App\Services\GeneralHouse\Payments\GhDriverCashDashboardService;
use App\Services\GeneralHouse\Reporting\GhCsvExportService;
use App\Services\GeneralHouse\Reporting\GhDriverCashReportService;
use App\Support\GeneralHouse\GhReportPeriod;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DriverCashReportController extends Controller
{
    public function __construct(
        private readonly GhDriverCashReportService $report,
        private readonly GhDriverCashDashboardService $dashboard,
        private readonly GhCsvExportService $csv,
    ) {}

    public function __invoke(GhDriverCashFilterRequest $request): View|StreamedResponse
    {
        $period = $request->period();
        $driverId = $request->validated('driver_id') !== null ? (int) $request->validated('driver_id') : null;
        $export = $request->validated('export');
        if ($export !== null) {
            return $this->export($export, $period, $driverId);
        }

        return view('gh.reports.driver-cash', [
            'period' => $period,
            'driverId' => $driverId,
            'totals' => $this->report->totals($period, $driverId),
            'outstanding' => $this->report->outstandingInvoices($driverId),
            'byDriver' => $this->report->byDriver($driverId),
            'byDate' => $this->report->byDate($period, $driverId),
            'byManager' => $this->report->byManager($period, $driverId),
            'driverOptions' => $this->dashboard->driverOptions(),
            'query' => array_filter(['preset' => $period->preset, 'from' => $period->fromDate, 'to' => $period->toDate, 'driver_id' => $driverId]),
            'displayTz' => $period->timezone,
        ]);
    }

    private function export(string $dataset, GhReportPeriod $period, ?int $driverId): StreamedResponse
    {
        return match ($dataset) {
            'outstanding' => $this->csv->download('gh_driver_cash_outstanding', [
                'Collection', 'Driver', 'Order', 'Invoice', 'Collected at (UTC)', 'Collected', 'Settled', 'Remaining', 'Status',
            ], $this->report->outstandingInvoicesForExport($driverId)->map(fn ($c) => [
                $c->reference, $c->driver?->name_en ?: $c->driver?->name_ar, $c->order?->reference, $c->order?->invoice?->number,
                $c->collected_at?->utc()->format('Y-m-d H:i:s'), (string) $c->amount, $c->settled_amount, $c->remaining_amount, $c->settlement_status,
            ])),
            'by_driver' => $this->csv->download('gh_driver_cash_by_driver', [
                'Driver', 'Collected total', 'Settled total', 'Outstanding',
            ], $this->report->byDriver($driverId)->map(fn ($d) => [
                $d->name_en ?: $d->name_ar, (string) $d->collected_total, (string) $d->settled_total, (string) $d->outstanding,
            ])),
            'by_date' => $this->csv->download('gh_driver_cash_by_date', [
                'Date ('.$period->timezone.')', 'Collected', 'Settled', 'Reversed',
            ], collect($this->report->byDate($period, $driverId))->map(fn ($r) => [$r['date'], $r['collected'], $r['settled'], $r['reversed']])),
            default => $this->csv->download('gh_driver_cash_by_manager', [
                'Manager', 'Settlements', 'Settled amount', 'Reversals', 'Reversed amount',
            ], collect($this->report->byManager($period, $driverId))->map(fn ($r) => [
                $r['manager']?->name_en ?: $r['manager']?->name_ar, $r['settled_count'], $r['settled'], $r['reversed_count'], $r['reversed'],
            ])),
        };
    }
}
