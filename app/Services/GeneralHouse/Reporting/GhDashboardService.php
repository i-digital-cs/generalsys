<?php

namespace App\Services\GeneralHouse\Reporting;

use App\Models\GeneralHouse\GhInvoice;
use App\Models\GeneralHouse\GhOrder;
use App\Models\GeneralHouse\GhOrderAttempt;
use App\Models\GeneralHouse\GhPayment;
use App\Models\User;
use App\Support\GeneralHouse\GhAttemptType;
use App\Support\GeneralHouse\GhInvoicePaymentStatus;
use App\Support\GeneralHouse\GhInvoiceStatus;
use App\Support\GeneralHouse\GhMoney;
use App\Support\GeneralHouse\GhOrderStatus;
use App\Support\GeneralHouse\GhPaymentMethod;
use App\Support\GeneralHouse\GhPaymentStatus;
use App\Support\GeneralHouse\GhReportFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Manager dashboard aggregates. Financial figures use issued invoices + CONFIRMED payments only.
 */
class GhDashboardService
{
    /**
     * @return array{
     *   filters: GhReportFilters,
     *   operational: array<string, int|null>,
     *   financial: array<string, string|int|null>,
     *   driverKpis: array{assigned_pickups: int, assigned_deliveries: int, completed_pickups: int, completed_deliveries: int, failed_attempts: int, pending_by_driver: list<array<string, mixed>>},
     *   status_distribution: list<array{status: string, count: int}>,
     *   order_trend: list<int>,
     *   recent_orders: Collection,
     *   pending_pickups: Collection,
     *   pending_deliveries: Collection,
     *   outstanding_invoices: Collection,
     *   notes: array{overdue_deliveries: string},
     * }
     */
    public function build(GhReportFilters $filters): array
    {
        $period = $filters->period;
        $nowUtc = Carbon::now('UTC');

        $op = [
            'total_orders' => $this->ordersCreatedInPeriod($filters)->count(),
            'new_orders' => $this->ordersCreatedInPeriod($filters)->count(),
            'awaiting_pickup' => $this->ordersSnapshot($filters)->where('status', GhOrderStatus::PICKUP)->count(),
            'working' => $this->ordersSnapshot($filters)->where('status', GhOrderStatus::WORKING)->count(),
            'delivery' => $this->ordersSnapshot($filters)->where('status', GhOrderStatus::DELIVERY)->count(),
            'completed' => $this->baseOrders($filters)
                ->whereNotNull('delivered_at')
                ->where('delivered_at', '>=', $period->startUtc)
                ->where('delivered_at', '<', $period->endUtc)
                ->count(),
            'cancelled' => $this->baseOrders($filters)
                ->whereNotNull('cancelled_at')
                ->where('cancelled_at', '>=', $period->startUtc)
                ->where('cancelled_at', '<', $period->endUtc)
                ->count(),
            'overdue_pickups' => $this->ordersSnapshot($filters)
                ->whereIn('status', [GhOrderStatus::LATER, GhOrderStatus::PICKUP])
                ->whereNotNull('pickup_ends_at')
                ->where('pickup_ends_at', '<', $nowUtc)
                ->count(),
            'overdue_deliveries' => null,
            'failed_pickup_attempts' => $this->failedAttempts($filters, GhAttemptType::PICKUP),
            'failed_delivery_attempts' => $this->failedAttempts($filters, GhAttemptType::DELIVERY),
        ];

        $issuedInPeriod = $this->issuedInvoices($filters)
            ->where('issued_at', '>=', $period->startUtc)
            ->where('issued_at', '<', $period->endUtc);

        $issuedValue = GhMoney::of((string) (clone $issuedInPeriod)->sum('total_payable'));
        $issuedCount = (clone $issuedInPeriod)->count();
        $avg = $issuedCount > 0
            ? GhMoney::roundHalfUp(GhMoney::div($issuedValue, (string) $issuedCount))
            : '0.00';

        $cashCollected = GhMoney::of((string) $this->confirmedPayments($filters)
            ->where('method', GhPaymentMethod::CASH)
            ->where('confirmed_at', '>=', $period->startUtc)
            ->where('confirmed_at', '<', $period->endUtc)
            ->sum('amount'));

        $totalCollected = GhMoney::of((string) $this->confirmedPayments($filters)
            ->where('confirmed_at', '>=', $period->startUtc)
            ->where('confirmed_at', '<', $period->endUtc)
            ->sum('amount'));

        $outstanding = $this->outstandingReceivables($filters);

        $fin = [
            'issued_invoice_value' => $issuedValue,
            'confirmed_cash_collections' => $cashCollected,
            'total_confirmed_collections' => $totalCollected,
            'outstanding_receivables' => $outstanding,
            'partially_paid_invoices' => $this->issuedSnapshot($filters)->where('payment_status', GhInvoicePaymentStatus::PARTIAL)->count(),
            'unpaid_invoices' => $this->issuedSnapshot($filters)->where('payment_status', GhInvoicePaymentStatus::UNPAID)->count(),
            'paid_invoices' => $this->issuedSnapshot($filters)->where('payment_status', GhInvoicePaymentStatus::PAID)->count(),
            'average_invoice_value' => $avg,
            'issued_invoice_count' => $issuedCount,
        ];

        $driverKpis = [
            'assigned_pickups' => $this->ordersSnapshot($filters)
                ->where('status', GhOrderStatus::PICKUP)
                ->whereNotNull('pickup_driver_id')
                ->when($filters->driverId, fn ($q) => $q->where('pickup_driver_id', $filters->driverId))
                ->count(),
            'assigned_deliveries' => $this->ordersSnapshot($filters)
                ->where('status', GhOrderStatus::DELIVERY)
                ->whereNotNull('delivery_driver_id')
                ->when($filters->driverId, fn ($q) => $q->where('delivery_driver_id', $filters->driverId))
                ->count(),
            'completed_pickups' => $this->baseOrders($filters)
                ->whereNotNull('picked_up_at')
                ->where('picked_up_at', '>=', $period->startUtc)
                ->where('picked_up_at', '<', $period->endUtc)
                ->when($filters->driverId, fn ($q) => $q->where('pickup_driver_id', $filters->driverId))
                ->count(),
            'completed_deliveries' => $this->baseOrders($filters)
                ->whereNotNull('delivered_at')
                ->where('delivered_at', '>=', $period->startUtc)
                ->where('delivered_at', '<', $period->endUtc)
                ->when($filters->driverId, fn ($q) => $q->where('delivery_driver_id', $filters->driverId))
                ->count(),
            'failed_attempts' => $this->failedAttempts($filters, null),
            'pending_by_driver' => $this->pendingByDriver($filters),
        ];

        $statusDistribution = $this->ordersSnapshot($filters)
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->orderBy('status')
            ->get()
            ->map(fn ($r) => ['status' => (string) $r->status, 'count' => (int) $r->c])
            ->all();

        return [
            'filters' => $filters,
            'operational' => $op,
            'financial' => $fin,
            'driverKpis' => $driverKpis,
            'status_distribution' => $statusDistribution,
            'order_trend' => $this->orderTrend($filters),
            'recent_orders' => $this->ordersCreatedInPeriod($filters)
                ->with(['customer:id,name_ar,name_en,type', 'zone:id,name_ar,name_en'])
                ->orderByDesc('id')
                ->limit(10)
                ->get(),
            'pending_pickups' => $this->ordersSnapshot($filters)
                ->with(['customer:id,name_ar,name_en', 'pickupDriver:id,name_ar,name_en'])
                ->where('status', GhOrderStatus::PICKUP)
                ->orderBy('pickup_starts_at')
                ->limit(10)
                ->get(),
            'pending_deliveries' => $this->ordersSnapshot($filters)
                ->with(['customer:id,name_ar,name_en', 'deliveryDriver:id,name_ar,name_en'])
                ->where('status', GhOrderStatus::DELIVERY)
                ->orderByDesc('invoice_issued_at')
                ->limit(10)
                ->get(),
            'outstanding_invoices' => $this->issuedSnapshot($filters)
                ->whereIn('payment_status', [GhInvoicePaymentStatus::UNPAID, GhInvoicePaymentStatus::PARTIAL])
                ->orderBy('issued_at')
                ->limit(10)
                ->get(['id', 'number', 'order_reference', 'customer_name_ar', 'customer_name_en', 'total_payable', 'payment_status', 'issued_at', 'currency']),
            'notes' => [
                'overdue_deliveries' => 'Unavailable: schema has no delivery due window (see docs/general-house/REPORTING_KPIS.md).',
            ],
        ];
    }

    /** @return Builder<GhOrder> */
    private function baseOrders(GhReportFilters $filters): Builder
    {
        $q = GhOrder::query();
        if ($filters->customerId) {
            $q->where('customer_id', $filters->customerId);
        }
        if ($filters->zoneId) {
            $q->where('zone_id', $filters->zoneId);
        }
        if ($filters->customerType) {
            $q->whereHas('customer', fn ($c) => $c->where('type', $filters->customerType));
        }
        if ($filters->orderStatus) {
            $q->where('status', $filters->orderStatus);
        }
        if ($filters->driverId) {
            $q->where(function ($inner) use ($filters) {
                $inner->where('pickup_driver_id', $filters->driverId)
                    ->orWhere('delivery_driver_id', $filters->driverId);
            });
        }

        return $q;
    }

    /** @return Builder<GhOrder> */
    private function ordersSnapshot(GhReportFilters $filters): Builder
    {
        // Snapshot: ignore period; keep dimension filters (except order_status is applied in base when set).
        return $this->baseOrders($filters);
    }

    /** @return Builder<GhOrder> */
    private function ordersCreatedInPeriod(GhReportFilters $filters): Builder
    {
        return $this->baseOrders($filters)
            ->where('created_at', '>=', $filters->period->startUtc)
            ->where('created_at', '<', $filters->period->endUtc);
    }

    /** @return Builder<GhInvoice> */
    private function issuedInvoices(GhReportFilters $filters): Builder
    {
        $q = GhInvoice::query()->where('status', GhInvoiceStatus::ISSUED);
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

        return $q;
    }

    /** @return Builder<GhInvoice> */
    private function issuedSnapshot(GhReportFilters $filters): Builder
    {
        return $this->issuedInvoices($filters);
    }

    /** @return Builder<GhPayment> */
    private function confirmedPayments(GhReportFilters $filters): Builder
    {
        $q = GhPayment::query()->where('status', GhPaymentStatus::CONFIRMED);
        if ($filters->customerId || $filters->zoneId || $filters->customerType || $filters->paymentStatus || $filters->driverId) {
            $q->whereHas('invoice', function ($inv) use ($filters) {
                $inv->where('status', GhInvoiceStatus::ISSUED);
                if ($filters->customerId) {
                    $inv->where('customer_id', $filters->customerId);
                }
                if ($filters->zoneId) {
                    $inv->where('zone_id', $filters->zoneId);
                }
                if ($filters->customerType) {
                    $inv->where('customer_type', $filters->customerType);
                }
                if ($filters->paymentStatus) {
                    $inv->where('payment_status', $filters->paymentStatus);
                }
                if ($filters->driverId) {
                    $inv->whereHas('order', function ($o) use ($filters) {
                        $o->where('pickup_driver_id', $filters->driverId)
                            ->orWhere('delivery_driver_id', $filters->driverId);
                    });
                }
            });
        }

        return $q;
    }

    private function outstandingReceivables(GhReportFilters $filters): string
    {
        $rows = $this->issuedSnapshot($filters)
            ->whereIn('payment_status', [GhInvoicePaymentStatus::UNPAID, GhInvoicePaymentStatus::PARTIAL])
            ->get(['id', 'total_payable']);

        $sum = '0.00';
        foreach ($rows as $inv) {
            $confirmed = GhMoney::of((string) GhPayment::query()
                ->where('invoice_id', $inv->id)
                ->where('status', GhPaymentStatus::CONFIRMED)
                ->sum('amount'));
            $remaining = GhMoney::roundHalfUp(GhMoney::sub(GhMoney::of((string) $inv->total_payable), $confirmed));
            if (GhMoney::cmp($remaining, '0') < 0) {
                $remaining = '0.00';
            }
            $sum = GhMoney::roundHalfUp(GhMoney::add($sum, $remaining));
        }

        return $sum;
    }

    private function failedAttempts(GhReportFilters $filters, ?string $type): int
    {
        $q = GhOrderAttempt::query()
            ->where('outcome', 'FAILED')
            ->where('occurred_at', '>=', $filters->period->startUtc)
            ->where('occurred_at', '<', $filters->period->endUtc);
        if ($type !== null) {
            $q->where('attempt_type', $type);
        }
        if ($filters->customerId || $filters->zoneId || $filters->customerType || $filters->orderStatus || $filters->driverId) {
            $q->whereHas('order', function ($o) use ($filters) {
                if ($filters->customerId) {
                    $o->where('customer_id', $filters->customerId);
                }
                if ($filters->zoneId) {
                    $o->where('zone_id', $filters->zoneId);
                }
                if ($filters->orderStatus) {
                    $o->where('status', $filters->orderStatus);
                }
                if ($filters->customerType) {
                    $o->whereHas('customer', fn ($c) => $c->where('type', $filters->customerType));
                }
                if ($filters->driverId) {
                    $o->where(function ($inner) use ($filters) {
                        $inner->where('pickup_driver_id', $filters->driverId)
                            ->orWhere('delivery_driver_id', $filters->driverId);
                    });
                }
            });
        }

        return $q->count();
    }

    /** @return list<array{driver_id: int, name_ar: string|null, name_en: string|null, pickups: int, deliveries: int}> */
    private function pendingByDriver(GhReportFilters $filters): array
    {
        $pickup = $this->ordersSnapshot($filters)
            ->where('status', GhOrderStatus::PICKUP)
            ->whereNotNull('pickup_driver_id')
            ->when($filters->driverId, fn ($q) => $q->where('pickup_driver_id', $filters->driverId))
            ->selectRaw('pickup_driver_id as driver_id, COUNT(*) as c')
            ->groupBy('pickup_driver_id')
            ->pluck('c', 'driver_id');

        $delivery = $this->ordersSnapshot($filters)
            ->where('status', GhOrderStatus::DELIVERY)
            ->whereNotNull('delivery_driver_id')
            ->when($filters->driverId, fn ($q) => $q->where('delivery_driver_id', $filters->driverId))
            ->selectRaw('delivery_driver_id as driver_id, COUNT(*) as c')
            ->groupBy('delivery_driver_id')
            ->pluck('c', 'driver_id');

        $ids = $pickup->keys()->merge($delivery->keys())->unique()->filter()->values();
        if ($ids->isEmpty()) {
            return [];
        }
        $users = User::query()->whereIn('id', $ids)->get(['id', 'name_ar', 'name_en'])->keyBy('id');

        $out = [];
        foreach ($ids as $id) {
            $u = $users->get($id);
            $out[] = [
                'driver_id' => (int) $id,
                'name_ar' => $u?->name_ar,
                'name_en' => $u?->name_en,
                'pickups' => (int) ($pickup[$id] ?? 0),
                'deliveries' => (int) ($delivery[$id] ?? 0),
            ];
        }
        usort($out, fn ($a, $b) => ($b['pickups'] + $b['deliveries']) <=> ($a['pickups'] + $a['deliveries']));

        return $out;
    }

    /** @return list<int> Daily new-order counts across the period (Dubai days). */
    private function orderTrend(GhReportFilters $filters): array
    {
        $tz = $filters->period->timezone;
        // PHP bucketing: portable across MySQL + SQLite test DB; period spans are capped.
        $bucket = [];
        $cursor = Carbon::parse($filters->period->fromDate, $tz)->startOfDay();
        $end = Carbon::parse($filters->period->toDate, $tz)->startOfDay();
        while ($cursor->lte($end)) {
            $bucket[$cursor->toDateString()] = 0;
            $cursor->addDay();
        }
        $this->ordersCreatedInPeriod($filters)->orderBy('id')->get(['created_at'])
            ->each(function (GhOrder $o) use (&$bucket, $tz) {
                $d = $o->created_at?->copy()->timezone($tz)->toDateString();
                if ($d !== null && array_key_exists($d, $bucket)) {
                    $bucket[$d]++;
                }
            });

        return array_values($bucket);
    }
}
