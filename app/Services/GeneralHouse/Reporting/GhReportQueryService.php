<?php

namespace App\Services\GeneralHouse\Reporting;

use App\Models\GeneralHouse\GhInvoice;
use App\Models\GeneralHouse\GhOrder;
use App\Models\GeneralHouse\GhOrderAttempt;
use App\Models\GeneralHouse\GhPayment;
use App\Services\GeneralHouse\Payments\GhPaymentBalanceService;
use App\Support\GeneralHouse\GhAttemptType;
use App\Support\GeneralHouse\GhCustomerType;
use App\Support\GeneralHouse\GhInvoicePaymentStatus;
use App\Support\GeneralHouse\GhInvoiceStatus;
use App\Support\GeneralHouse\GhMoney;
use App\Support\GeneralHouse\GhOrderStatus;
use App\Support\GeneralHouse\GhPaymentStatus;
use App\Support\GeneralHouse\GhReportFilters;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Paginated / exportable report queries.
 * Remaining due / credits / refunds consume GhPaymentBalanceService (GH-10 SSOT).
 */
class GhReportQueryService
{
    public function __construct(
        private readonly GhPaymentBalanceService $balances,
    ) {}

    public function invoices(GhReportFilters $filters, int $perPage = 25): LengthAwarePaginator
    {
        return $this->invoiceBase($filters)
            ->withSum($this->confirmedPaymentsSumRelation(), 'amount')
            ->orderByDesc('issued_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    /** @return Collection<int, array<string, mixed>> */
    public function invoicesForExport(GhReportFilters $filters): Collection
    {
        return $this->mapInvoiceRows($this->invoiceBase($filters)->orderByDesc('issued_at')->limit(5000)->get());
    }

    public function collections(GhReportFilters $filters, int $perPage = 25): LengthAwarePaginator
    {
        return $this->collectionBase($filters)
            ->with(['invoice:id,number,order_reference,customer_name_ar,customer_name_en', 'collector:id,name_ar,name_en'])
            ->orderByDesc('confirmed_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    /** @return Collection<int, GhPayment> */
    public function collectionsForExport(GhReportFilters $filters): Collection
    {
        return $this->collectionBase($filters)
            ->with(['invoice:id,number,order_reference,customer_name_ar,customer_name_en', 'collector:id,name_ar,name_en'])
            ->orderByDesc('confirmed_at')
            ->limit(5000)
            ->get();
    }

    public function outstanding(GhReportFilters $filters, int $perPage = 25): LengthAwarePaginator
    {
        return $this->outstandingBase($filters)
            ->withSum($this->confirmedPaymentsSumRelation(), 'amount')
            ->orderBy('issued_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    /** @return Collection<int, array<string, mixed>> */
    public function outstandingForExport(GhReportFilters $filters): Collection
    {
        return $this->mapOutstandingRows($this->outstandingBase($filters)->orderBy('issued_at')->limit(5000)->get());
    }

    public function b2bReceivables(GhReportFilters $filters, int $perPage = 25): LengthAwarePaginator
    {
        return $this->b2bGrouped($filters)->paginate($perPage)->withQueryString();
    }

    /** @return Collection<int, object> */
    public function b2bReceivablesForExport(GhReportFilters $filters): Collection
    {
        return $this->b2bGrouped($filters)->limit(5000)->get();
    }

    public function orders(GhReportFilters $filters, int $perPage = 25): LengthAwarePaginator
    {
        return $this->orderReportBase($filters)
            ->with([
                'customer:id,name_ar,name_en,type',
                'zone:id,name_ar,name_en',
                'pickupDriver:id,name_ar,name_en',
                'deliveryDriver:id,name_ar,name_en',
            ])
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /** @return Collection<int, GhOrder> */
    public function ordersForExport(GhReportFilters $filters): Collection
    {
        return $this->orderReportBase($filters)
            ->with([
                'customer:id,name_ar,name_en,type',
                'zone:id,name_ar,name_en',
                'pickupDriver:id,name_ar,name_en',
                'deliveryDriver:id,name_ar,name_en',
            ])
            ->orderByDesc('id')
            ->limit(5000)
            ->get();
    }

    /**
     * @return array{
     *   scheduled: int,
     *   completed: int,
     *   failed_attempts: int,
     *   overdue: int,
     *   avg_pickup_lag_minutes: float|null,
     * }
     */
    public function pickupPerformance(GhReportFilters $filters): array
    {
        $period = $filters->period;
        $nowUtc = Carbon::now('UTC');

        $scheduled = $this->orderDim($filters)
            ->where('pickup_starts_at', '>=', $period->startUtc)
            ->where('pickup_starts_at', '<', $period->endUtc)
            ->count();

        $completedQ = $this->orderDim($filters)
            ->whereNotNull('picked_up_at')
            ->where('picked_up_at', '>=', $period->startUtc)
            ->where('picked_up_at', '<', $period->endUtc);

        $completed = (clone $completedQ)->count();

        $avgLag = null;
        if ($completed > 0) {
            $lags = (clone $completedQ)
                ->whereNotNull('pickup_starts_at')
                ->get(['picked_up_at', 'pickup_starts_at'])
                ->map(fn (GhOrder $o) => $o->picked_up_at->diffInMinutes($o->pickup_starts_at, false));
            // diffInMinutes signed: positive if picked after start
            $avgLag = round($lags->avg() ?? 0, 1);
        }

        $failed = GhOrderAttempt::query()
            ->where('attempt_type', GhAttemptType::PICKUP)
            ->where('outcome', 'FAILED')
            ->where('occurred_at', '>=', $period->startUtc)
            ->where('occurred_at', '<', $period->endUtc)
            ->whereHas('order', fn ($o) => $this->applyOrderDims($o, $filters))
            ->count();

        $overdue = $this->orderDim($filters)
            ->whereIn('status', [GhOrderStatus::LATER, GhOrderStatus::PICKUP])
            ->whereNotNull('pickup_ends_at')
            ->where('pickup_ends_at', '<', $nowUtc)
            ->count();

        return [
            'scheduled' => $scheduled,
            'completed' => $completed,
            'failed_attempts' => $failed,
            'overdue' => $overdue,
            'avg_pickup_lag_minutes' => $avgLag,
        ];
    }

    /**
     * @return array{
     *   assignments: int,
     *   completed: int,
     *   failed_attempts: int,
     *   pending: int,
     *   overdue: null,
     *   overdue_note: string,
     *   avg_delivery_hours: float|null,
     * }
     */
    public function deliveryPerformance(GhReportFilters $filters): array
    {
        $period = $filters->period;

        $assignments = $this->orderDim($filters)
            ->whereNotNull('delivery_driver_id')
            ->where(function ($q) use ($period) {
                $q->where(function ($inner) use ($period) {
                    $inner->whereNotNull('invoice_issued_at')
                        ->where('invoice_issued_at', '>=', $period->startUtc)
                        ->where('invoice_issued_at', '<', $period->endUtc);
                })->orWhere('status', GhOrderStatus::DELIVERY);
            })
            ->count();

        // Cleaner: pending snapshot + completed in period.
        $pending = $this->orderDim($filters)->where('status', GhOrderStatus::DELIVERY)->count();

        $completedQ = $this->orderDim($filters)
            ->whereNotNull('delivered_at')
            ->where('delivered_at', '>=', $period->startUtc)
            ->where('delivered_at', '<', $period->endUtc);

        $completed = (clone $completedQ)->count();
        $avgHours = null;
        if ($completed > 0) {
            $hours = (clone $completedQ)
                ->whereNotNull('invoice_issued_at')
                ->get(['delivered_at', 'invoice_issued_at'])
                ->map(fn (GhOrder $o) => $o->delivered_at->floatDiffInHours($o->invoice_issued_at));
            $avgHours = round((float) ($hours->avg() ?? 0), 2);
        }

        $failed = GhOrderAttempt::query()
            ->where('attempt_type', GhAttemptType::DELIVERY)
            ->where('outcome', 'FAILED')
            ->where('occurred_at', '>=', $period->startUtc)
            ->where('occurred_at', '<', $period->endUtc)
            ->whereHas('order', fn ($o) => $this->applyOrderDims($o, $filters))
            ->count();

        return [
            'assignments' => $assignments,
            'completed' => $completed,
            'failed_attempts' => $failed,
            'pending' => $pending,
            'overdue' => null,
            'overdue_note' => 'No delivery due window in schema — overdue deliveries unavailable.',
            'avg_delivery_hours' => $avgHours,
        ];
    }

    public function remainingForInvoice(GhInvoice $invoice, ?string $confirmed = null): string
    {
        // $confirmed retained for call-site BC; remaining_due is balance SSOT (credits + refunds).
        unset($confirmed);

        return $this->balances->forInvoice($invoice)['remaining_due'];
    }

    public function confirmedForInvoice(GhInvoice $invoice): string
    {
        if (array_key_exists('payments_sum_amount', $invoice->getAttributes())) {
            return GhMoney::of((string) ($invoice->payments_sum_amount ?? '0'));
        }

        return GhMoney::of((string) GhPayment::query()
            ->where('invoice_id', $invoice->id)
            ->where('status', GhPaymentStatus::CONFIRMED)
            ->sum('amount'));
    }

    /**
     * @return array<string, \Closure>
     */
    private function confirmedPaymentsSumRelation(): array
    {
        return [
            'payments as payments_sum_amount' => fn ($q) => $q->where('status', GhPaymentStatus::CONFIRMED),
        ];
    }

    /**
     * @param  list<int>  $invoiceIds
     * @return array<int, string> invoice_id => confirmed total
     */
    private function confirmedTotalsByInvoiceIds(array $invoiceIds): array
    {
        if ($invoiceIds === []) {
            return [];
        }

        $rows = GhPayment::query()
            ->selectRaw('invoice_id, SUM(amount) as confirmed_total')
            ->whereIn('invoice_id', $invoiceIds)
            ->where('status', GhPaymentStatus::CONFIRMED)
            ->groupBy('invoice_id')
            ->pluck('confirmed_total', 'invoice_id');

        $out = [];
        foreach ($invoiceIds as $id) {
            $out[(int) $id] = GhMoney::of((string) ($rows[$id] ?? '0'));
        }

        return $out;
    }

    public function daysOutstanding(GhInvoice $invoice, string $tz): ?int
    {
        if ($invoice->issued_at === null || $invoice->payment_status === GhInvoicePaymentStatus::PAID) {
            return null;
        }
        $issuedDay = $invoice->issued_at->copy()->timezone($tz)->startOfDay();
        $today = Carbon::now($tz)->startOfDay();

        return (int) $issuedDay->diffInDays($today);
    }

    /** @return Builder<GhInvoice> */
    private function invoiceBase(GhReportFilters $filters): Builder
    {
        $q = GhInvoice::query()->where('status', GhInvoiceStatus::ISSUED);
        $this->applyInvoiceDims($q, $filters);
        $q->where('issued_at', '>=', $filters->period->startUtc)
            ->where('issued_at', '<', $filters->period->endUtc);

        return $q;
    }

    /** @return Builder<GhPayment> */
    private function collectionBase(GhReportFilters $filters): Builder
    {
        $q = GhPayment::query()
            ->where('status', GhPaymentStatus::CONFIRMED)
            ->where('confirmed_at', '>=', $filters->period->startUtc)
            ->where('confirmed_at', '<', $filters->period->endUtc);

        if ($filters->customerId || $filters->zoneId || $filters->customerType || $filters->paymentStatus || $filters->driverId) {
            $q->whereHas('invoice', function ($inv) use ($filters) {
                $this->applyInvoiceDims($inv, $filters);
            });
        }

        return $q;
    }

    /** @return Builder<GhInvoice> */
    private function outstandingBase(GhReportFilters $filters): Builder
    {
        $q = GhInvoice::query()
            ->where('status', GhInvoiceStatus::ISSUED)
            ->whereIn('payment_status', [GhInvoicePaymentStatus::UNPAID, GhInvoicePaymentStatus::PARTIAL]);
        $this->applyInvoiceDims($q, $filters);

        return $q;
    }

    private function b2bGrouped(GhReportFilters $filters): Builder
    {
        $confirmedSub = GhPayment::query()
            ->selectRaw('invoice_id, SUM(amount) as confirmed_total')
            ->where('status', GhPaymentStatus::CONFIRMED)
            ->groupBy('invoice_id');

        $q = GhInvoice::query()
            ->from('gh_invoices as i')
            ->leftJoinSub($confirmedSub, 'p', 'p.invoice_id', '=', 'i.id')
            ->where('i.status', GhInvoiceStatus::ISSUED)
            ->where('i.customer_type', GhCustomerType::B2B)
            ->selectRaw('
                i.customer_id,
                MAX(i.customer_name_ar) as customer_name_ar,
                MAX(i.customer_name_en) as customer_name_en,
                i.contract_id,
                MAX(i.contract_reference) as contract_reference,
                SUM(i.total_payable) as issued_total,
                SUM(COALESCE(p.confirmed_total, 0)) as confirmed_total,
                SUM(CASE WHEN i.total_payable - COALESCE(p.confirmed_total, 0) > 0
                    THEN i.total_payable - COALESCE(p.confirmed_total, 0) ELSE 0 END) as outstanding_total
            ')
            ->groupBy('i.customer_id', 'i.contract_id')
            ->orderBy('customer_name_en');

        if ($filters->customerId) {
            $q->where('i.customer_id', $filters->customerId);
        }
        if ($filters->zoneId) {
            $q->where('i.zone_id', $filters->zoneId);
        }
        // Period applies to issued_at for B2B issued totals in range; outstanding still from those invoices.
        $q->where('i.issued_at', '>=', $filters->period->startUtc)
            ->where('i.issued_at', '<', $filters->period->endUtc);

        return $q;
    }

    /** @return Builder<GhOrder> */
    private function orderReportBase(GhReportFilters $filters): Builder
    {
        $q = $this->orderDim($filters);
        $q->where('created_at', '>=', $filters->period->startUtc)
            ->where('created_at', '<', $filters->period->endUtc);

        return $q;
    }

    /** @return Builder<GhOrder> */
    private function orderDim(GhReportFilters $filters): Builder
    {
        $q = GhOrder::query();
        $this->applyOrderDims($q, $filters);

        return $q;
    }

    /** @param  Builder<GhOrder>  $q */
    private function applyOrderDims(Builder $q, GhReportFilters $filters): void
    {
        if ($filters->customerId) {
            $q->where('customer_id', $filters->customerId);
        }
        if ($filters->zoneId) {
            $q->where('zone_id', $filters->zoneId);
        }
        if ($filters->orderStatus) {
            $q->where('status', $filters->orderStatus);
        }
        if ($filters->customerType) {
            $q->whereHas('customer', fn ($c) => $c->where('type', $filters->customerType));
        }
        if ($filters->driverId) {
            $q->where(function ($inner) use ($filters) {
                $inner->where('pickup_driver_id', $filters->driverId)
                    ->orWhere('delivery_driver_id', $filters->driverId);
            });
        }
    }

    /** @param  Builder<GhInvoice>  $q */
    private function applyInvoiceDims(Builder $q, GhReportFilters $filters): void
    {
        if ($filters->customerId) {
            $q->where('customer_id', $filters->customerId);
        }
        if ($filters->zoneId) {
            $q->where('zone_id', $filters->zoneId);
        }
        if ($filters->customerType) {
            $q->where('customer_type', $filters->customerType);
        }
        if ($filters->paymentStatus) {
            $q->where('payment_status', $filters->paymentStatus);
        }
        if ($filters->driverId) {
            $q->whereHas('order', function ($o) use ($filters) {
                $o->where('pickup_driver_id', $filters->driverId)
                    ->orWhere('delivery_driver_id', $filters->driverId);
            });
        }
    }

    /**
     * @param  Collection<int, GhInvoice>  $invoices
     * @return Collection<int, array<string, mixed>>
     */
    private function mapInvoiceRows(Collection $invoices): Collection
    {
        $totals = $this->confirmedTotalsByInvoiceIds($invoices->pluck('id')->all());

        return $invoices->map(function (GhInvoice $inv) use ($totals) {
            $confirmed = $totals[(int) $inv->id] ?? '0.00';

            return [
                'number' => $inv->number,
                'order_reference' => $inv->order_reference,
                'customer' => $inv->customer_name_en ?: $inv->customer_name_ar,
                'issued_at' => $inv->issued_at,
                'total_payable' => GhMoney::of((string) $inv->total_payable),
                'confirmed' => $confirmed,
                'remaining' => $this->remainingForInvoice($inv, $confirmed),
                'payment_status' => $inv->payment_status,
            ];
        });
    }

    /**
     * @param  Collection<int, GhInvoice>  $invoices
     * @return Collection<int, array<string, mixed>>
     */
    private function mapOutstandingRows(Collection $invoices): Collection
    {
        $tz = (string) config('general_house.timezone', 'Asia/Dubai');
        $totals = $this->confirmedTotalsByInvoiceIds($invoices->pluck('id')->all());

        return $invoices->map(function (GhInvoice $inv) use ($tz, $totals) {
            $confirmed = $totals[(int) $inv->id] ?? '0.00';

            return [
                'customer' => $inv->customer_name_en ?: $inv->customer_name_ar,
                'number' => $inv->number,
                'total_payable' => GhMoney::of((string) $inv->total_payable),
                'confirmed' => $confirmed,
                'remaining' => $this->remainingForInvoice($inv, $confirmed),
                'days_outstanding' => $this->daysOutstanding($inv, $tz),
                'payment_status' => $inv->payment_status,
            ];
        });
    }
}
