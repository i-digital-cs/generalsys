<?php

namespace App\Services\GeneralHouse\Invoices;

use App\Models\GeneralHouse\GhInvoice;
use App\Services\GeneralHouse\Payments\GhCashSettlementQueryService;
use App\Support\GeneralHouse\GhCashSettlementStatus;
use App\Support\GeneralHouse\GhMoney;
use App\Support\GeneralHouse\GhPaymentStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Manager invoice list with payment and driver-cash settlement columns.
 * Settlement figures come from GhCashSettlementQueryService (single source of truth).
 */
final class GhInvoiceListService
{
    public function __construct(
        private readonly GhCashSettlementQueryService $settlement,
    ) {}

    /**
     * @param  array<string, mixed>  $filters  validated GhInvoiceIndexRequest data
     * @return LengthAwarePaginator<int, GhInvoice>
     */
    public function paginate(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $page = $this->query($filters)
            ->orderByDesc('gh_invoices.id')
            ->paginate(max(1, min(50, $perPage)))
            ->withQueryString();
        $page->getCollection()->each(function (GhInvoice $invoice) {
            $collected = GhMoney::of((string) ($invoice->driver_cash_collected ?? '0'));
            $settled = GhMoney::of((string) ($invoice->driver_cash_settled ?? '0'));
            $invoice->setAttribute('driver_cash_collected', $collected);
            $invoice->setAttribute('driver_cash_settled', $settled);
            $invoice->setAttribute('driver_cash_outstanding', GhMoney::of(GhMoney::sub($collected, $settled, 2)));
            $invoice->setAttribute('cash_settlement_status', GhCashSettlementStatus::derive($collected, $settled));
        });

        return $page;
    }

    /** @param  array<string, mixed>  $filters */
    public function query(array $filters): Builder
    {
        $query = GhInvoice::query()
            ->select('gh_invoices.*')
            ->with([
                'order:id,reference',
                'payments' => fn ($q) => $q->where('status', GhPaymentStatus::CONFIRMED)->select(['id', 'invoice_id', 'method']),
            ])
            ->withSum(['payments as payments_sum_amount' => fn ($q) => $q->where('status', GhPaymentStatus::CONFIRMED)], 'amount');
        $this->settlement->withOrderSettlementTotals($query, 'gh_invoices.order_id');

        $this->applyTextFilters($query, $filters);
        $this->applyDateFilters($query, $filters);

        if (! empty($filters['driver_id'])) {
            $query->whereExists(fn ($q) => $q->from('gh_driver_cash_collections as dcc_f')
                ->whereColumn('dcc_f.order_id', 'gh_invoices.order_id')
                ->where('dcc_f.driver_id', (int) $filters['driver_id']));
        }
        if (! empty($filters['settlement_status'])) {
            $this->settlement->filterBySettlementStatus($query, 'gh_invoices.order_id', (string) $filters['settlement_status']);
        }

        return $query;
    }

    /** @param  array<string, mixed>  $filters */
    private function applyTextFilters(Builder $query, array $filters): void
    {
        $like = fn (string $v) => '%'.addcslashes($v, '%_\\').'%';
        $query
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('gh_invoices.status', $s))
            ->when($filters['payment_status'] ?? null, fn ($q, $s) => $q->where('gh_invoices.payment_status', $s))
            ->when($filters['number'] ?? null, fn ($q, $v) => $q->where('gh_invoices.number', 'like', $like($v)))
            ->when($filters['order'] ?? null, fn ($q, $v) => $q->where('gh_invoices.order_reference', 'like', $like($v)))
            ->when($filters['customer'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('gh_invoices.customer_name_en', 'like', $like($v))
                ->orWhere('gh_invoices.customer_name_ar', 'like', $like($v))
                ->orWhere('gh_invoices.customer_phone', 'like', $like($v))))
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('gh_invoices.number', 'like', $like($term))
                ->orWhere('gh_invoices.order_reference', 'like', $like($term))
                ->orWhere('gh_invoices.customer_name_en', 'like', $like($term))
                ->orWhere('gh_invoices.customer_name_ar', 'like', $like($term))));
    }

    /** @param  array<string, mixed>  $filters  from/to are Dubai business dates; invoice date = issued_at (drafts: created_at) */
    private function applyDateFilters(Builder $query, array $filters): void
    {
        $tz = (string) config('general_house.timezone', 'Asia/Dubai');
        $dateColumn = 'COALESCE(gh_invoices.issued_at, gh_invoices.created_at)';
        if (! empty($filters['from'])) {
            $query->whereRaw("{$dateColumn} >= ?", [Carbon::parse($filters['from'].' 00:00:00', $tz)->utc()->format('Y-m-d H:i:s')]);
        }
        if (! empty($filters['to'])) {
            $query->whereRaw("{$dateColumn} < ?", [Carbon::parse($filters['to'].' 00:00:00', $tz)->addDay()->utc()->format('Y-m-d H:i:s')]);
        }
    }
}
