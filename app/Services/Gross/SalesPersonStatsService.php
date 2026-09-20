<?php

namespace App\Services\Gross;

use App\Models\Gross\GrossSalesInvoice;
use App\Models\User;
use App\Support\GrossAccess;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SalesPersonStatsService
{
    /**
     * @param  array{month?: string, from?: string, to?: string, currency?: string, sales_user_id?: string|int|null}  $filters
     * @return array{
     *     from: Carbon,
     *     to: Carbon,
     *     month: string,
     *     currency: string,
     *     sales_user_id: string,
     *     filters: array{month: string, from: string, to: string, currency: string, sales_user_id: string}
     * }
     */
    public function resolvePeriod(array $filters = []): array
    {
        $currency = strtoupper(trim((string) ($filters['currency'] ?? 'EGP')));
        if (! in_array($currency, ['EGP', 'AED'], true)) {
            $currency = 'EGP';
        }

        $fromInput = trim((string) ($filters['from'] ?? ''));
        $toInput = trim((string) ($filters['to'] ?? ''));
        $monthInput = trim((string) ($filters['month'] ?? ''));

        if ($fromInput !== '' || $toInput !== '') {
            $from = $fromInput !== ''
                ? Carbon::parse($fromInput)->startOfDay()
                : Carbon::parse($toInput)->startOfMonth()->startOfDay();
            $to = $toInput !== ''
                ? Carbon::parse($toInput)->endOfDay()
                : Carbon::parse($fromInput)->endOfMonth()->endOfDay();
            if ($from->gt($to)) {
                [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
            }
            $month = $from->format('Y-m');
        } else {
            $month = preg_match('/^\d{4}-\d{2}$/', $monthInput) === 1
                ? $monthInput
                : now()->format('Y-m');
            $from = Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfDay();
            $to = $from->copy()->endOfMonth()->endOfDay();
        }

        $salesUserId = trim((string) ($filters['sales_user_id'] ?? ''));
        if ($salesUserId !== '' && $salesUserId !== 'unassigned' && ! ctype_digit($salesUserId)) {
            $salesUserId = '';
        }

        return [
            'from' => $from,
            'to' => $to,
            'month' => $month,
            'currency' => $currency,
            'sales_user_id' => $salesUserId,
            'filters' => [
                'month' => $month,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'currency' => $currency,
                'sales_user_id' => $salesUserId,
            ],
        ];
    }

    /**
     * Options for the sales-person filter (Manager/Accountant only).
     *
     * @return Collection<int, User>
     */
    public function salesPersonFilterOptions(?User $viewer): Collection
    {
        if (! $this->mayViewAll($viewer)) {
            return collect();
        }

        $invoiceUserIds = GrossSalesInvoice::query()
            ->whereIn('status', [
                GrossSalesInvoice::STATUS_CONFIRMED,
                GrossSalesInvoice::STATUS_DELIVERED,
                GrossSalesInvoice::STATUS_REFUSED,
            ])
            ->whereNotNull('sales_user_id')
            ->distinct()
            ->pluck('sales_user_id');

        $salesRoleIds = User::query()
            ->where('status', User::STATUS_ACTIVE)
            ->where('role', User::ROLE_SALES)
            ->pluck('id');

        $ids = $invoiceUserIds->merge($salesRoleIds)->unique()->filter()->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return User::query()
            ->whereIn('id', $ids)
            ->orderBy('name_en')
            ->orderBy('name_ar')
            ->get();
    }

    public function mayViewAll(?User $viewer): bool
    {
        return GrossAccess::mayManageMasterData($viewer);
    }

    public function assertCanViewSubject(?User $viewer, ?User $subject): void
    {
        abort_unless(GrossAccess::maySell($viewer), 403);

        if ($this->mayViewAll($viewer)) {
            return;
        }

        abort_unless($viewer !== null && $subject !== null && (int) $viewer->id === (int) $subject->id, 403);
    }

    public function assertCanViewUnassigned(?User $viewer): void
    {
        abort_unless($this->mayViewAll($viewer), 403);
    }

    /**
     * @param  array{month?: string, from?: string, to?: string, currency?: string}  $filters
     * @return array{
     *     filters: array{month: string, from: string, to: string, currency: string},
     *     rows: list<array<string, mixed>>,
     *     totals: array<string, mixed>
     * }
     */
    public function summarize(?User $viewer, array $filters = []): array
    {
        abort_unless(GrossAccess::maySell($viewer), 403);

        $period = $this->resolvePeriod($filters);
        $query = $this->baseInvoiceQuery($period['from'], $period['to'], $period['currency']);
        $this->scopeViewer($query, $viewer);
        $this->scopeSalesUserFilter($query, $viewer, $period['sales_user_id']);

        $grouped = $query
            ->selectRaw('sales_user_id')
            ->selectRaw('COUNT(*) as invoices_count')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as delivered_count', [GrossSalesInvoice::STATUS_DELIVERED])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as refused_count', [GrossSalesInvoice::STATUS_REFUSED])
            ->selectRaw('SUM(CASE WHEN cod_collection_status = ? THEN 1 ELSE 0 END) as cod_pending_count', [GrossSalesInvoice::COD_PENDING])
            ->selectRaw('SUM('.$this->netMerchandiseSql().') as net_merchandise')
            ->selectRaw('SUM(returned_merchandise) as returned_merchandise')
            ->selectRaw('SUM('.$this->originalMerchandiseSql().') as original_merchandise')
            ->selectRaw('SUM(CASE WHEN cod_collection_status = ? THEN COALESCE(cod_collected_amount, 0) ELSE 0 END) as cod_collected', [GrossSalesInvoice::COD_COLLECTED])
            ->groupBy('sales_user_id')
            ->get();

        $userIds = $grouped->pluck('sales_user_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();
        $users = User::query()->whereIn('id', $userIds)->get()->keyBy('id');

        $rows = $grouped
            ->map(function ($row) use ($users, $period) {
                $userId = $row->sales_user_id !== null ? (int) $row->sales_user_id : null;
                $user = $userId !== null ? $users->get($userId) : null;
                $invoices = (int) $row->invoices_count;
                $refused = (int) $row->refused_count;

                return [
                    'sales_user_id' => $userId,
                    'name' => $user?->localizedName() ?? __('gross.sales_stats.unassigned'),
                    'is_unassigned' => $userId === null,
                    'invoices_count' => $invoices,
                    'delivered_count' => (int) $row->delivered_count,
                    'refused_count' => $refused,
                    'cod_pending_count' => (int) $row->cod_pending_count,
                    'net_merchandise' => $this->money($row->net_merchandise),
                    'returned_merchandise' => $this->money($row->returned_merchandise),
                    'original_merchandise' => $this->money($row->original_merchandise),
                    'cod_collected' => $this->money($row->cod_collected),
                    'refuse_rate' => $invoices > 0 ? round(($refused / $invoices) * 100, 1) : 0.0,
                    'url' => $userId !== null
                        ? route('gross.sales.stats.show', array_merge($period['filters'], ['user' => $userId]))
                        : route('gross.sales.stats.unassigned', $period['filters']),
                ];
            })
            ->sortByDesc(fn (array $row) => (float) $row['net_merchandise'])
            ->values()
            ->all();

        return [
            'filters' => $period['filters'],
            'rows' => $rows,
            'totals' => $this->totalsFromRows($rows),
        ];
    }

    /**
     * @param  array{month?: string, from?: string, to?: string, currency?: string}  $filters
     * @return array{
     *     filters: array{month: string, from: string, to: string, currency: string},
     *     subject: User|null,
     *     is_unassigned: bool,
     *     name: string,
     *     kpis: array<string, mixed>,
     *     invoices: LengthAwarePaginator
     * }
     */
    public function forUser(?User $viewer, ?User $subject, array $filters = [], bool $unassigned = false): array
    {
        if ($unassigned) {
            $this->assertCanViewUnassigned($viewer);
        } else {
            $this->assertCanViewSubject($viewer, $subject);
        }

        $period = $this->resolvePeriod($filters);
        $query = $this->baseInvoiceQuery($period['from'], $period['to'], $period['currency']);

        if ($unassigned) {
            $query->whereNull('sales_user_id');
        } else {
            $query->where('sales_user_id', $subject->id);
        }

        $agg = (clone $query)
            ->selectRaw('COUNT(*) as invoices_count')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as delivered_count', [GrossSalesInvoice::STATUS_DELIVERED])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as refused_count', [GrossSalesInvoice::STATUS_REFUSED])
            ->selectRaw('SUM(CASE WHEN cod_collection_status = ? THEN 1 ELSE 0 END) as cod_pending_count', [GrossSalesInvoice::COD_PENDING])
            ->selectRaw('SUM('.$this->netMerchandiseSql().') as net_merchandise')
            ->selectRaw('SUM(returned_merchandise) as returned_merchandise')
            ->selectRaw('SUM('.$this->originalMerchandiseSql().') as original_merchandise')
            ->selectRaw('SUM(CASE WHEN cod_collection_status = ? THEN COALESCE(cod_collected_amount, 0) ELSE 0 END) as cod_collected', [GrossSalesInvoice::COD_COLLECTED])
            ->first();

        $invoicesCount = (int) ($agg->invoices_count ?? 0);
        $refusedCount = (int) ($agg->refused_count ?? 0);

        $invoices = $query
            ->with(['customer', 'shippingCompany', 'salesUser'])
            ->orderByDesc(DB::raw('COALESCE(confirmed_at, created_at)'))
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return [
            'filters' => $period['filters'],
            'subject' => $subject,
            'is_unassigned' => $unassigned,
            'name' => $unassigned
                ? __('gross.sales_stats.unassigned')
                : ($subject?->localizedName() ?? '—'),
            'kpis' => [
                'invoices_count' => $invoicesCount,
                'delivered_count' => (int) ($agg->delivered_count ?? 0),
                'refused_count' => $refusedCount,
                'cod_pending_count' => (int) ($agg->cod_pending_count ?? 0),
                'net_merchandise' => $this->money($agg->net_merchandise ?? 0),
                'returned_merchandise' => $this->money($agg->returned_merchandise ?? 0),
                'original_merchandise' => $this->money($agg->original_merchandise ?? 0),
                'cod_collected' => $this->money($agg->cod_collected ?? 0),
                'refuse_rate' => $invoicesCount > 0 ? round(($refusedCount / $invoicesCount) * 100, 1) : 0.0,
            ],
            'invoices' => $invoices,
        ];
    }

    /**
     * @return array{
     *     currency: string,
     *     month: string,
     *     from: string,
     *     to: string,
     *     net_merchandise: string,
     *     invoices_count: int,
     *     top_seller_name: string|null,
     *     top_seller_amount: string|null,
     *     own_only: bool,
     *     stats_url: string
     * }
     */
    public function dashboardCards(User $viewer): array
    {
        abort_unless(GrossAccess::maySell($viewer), 403);

        $period = $this->resolvePeriod([
            'month' => now()->format('Y-m'),
            'currency' => 'EGP',
        ]);
        $summary = $this->summarize($viewer, $period['filters']);
        $totals = $summary['totals'];
        $top = $summary['rows'][0] ?? null;

        return [
            'currency' => 'EGP',
            'month' => $period['month'],
            'from' => $period['filters']['from'],
            'to' => $period['filters']['to'],
            'net_merchandise' => $totals['net_merchandise'],
            'invoices_count' => $totals['invoices_count'],
            'top_seller_name' => $this->mayViewAll($viewer) ? ($top['name'] ?? null) : null,
            'top_seller_amount' => $this->mayViewAll($viewer) ? ($top['net_merchandise'] ?? null) : null,
            'own_only' => ! $this->mayViewAll($viewer),
            'stats_url' => route('gross.sales.stats', $period['filters']),
        ];
    }

    private function baseInvoiceQuery(Carbon $from, Carbon $to, string $currency): Builder
    {
        return GrossSalesInvoice::query()
            ->where('currency', $currency)
            ->whereIn('status', [
                GrossSalesInvoice::STATUS_CONFIRMED,
                GrossSalesInvoice::STATUS_DELIVERED,
                GrossSalesInvoice::STATUS_REFUSED,
            ])
            ->whereRaw('COALESCE(confirmed_at, created_at) >= ?', [$from->toDateTimeString()])
            ->whereRaw('COALESCE(confirmed_at, created_at) <= ?', [$to->toDateTimeString()]);
    }

    private function scopeViewer(Builder $query, ?User $viewer): void
    {
        if ($this->mayViewAll($viewer)) {
            return;
        }

        $query->where('sales_user_id', $viewer?->id);
    }

    private function scopeSalesUserFilter(Builder $query, ?User $viewer, string $salesUserId): void
    {
        if ($salesUserId === '' || ! $this->mayViewAll($viewer)) {
            return;
        }

        if ($salesUserId === 'unassigned') {
            $query->whereNull('sales_user_id');

            return;
        }

        $query->where('sales_user_id', (int) $salesUserId);
    }

    private function netMerchandiseSql(): string
    {
        return 'CASE WHEN (net_subtotal - net_discount) > 0 THEN ROUND(net_subtotal - net_discount, 2) ELSE 0 END';
    }

    private function originalMerchandiseSql(): string
    {
        return 'CASE '
            .'WHEN discount_percent > 0 THEN CASE WHEN ROUND(subtotal * (1 - discount_percent / 100), 2) > 0 '
            .'THEN ROUND(subtotal * (1 - discount_percent / 100), 2) ELSE 0 END '
            .'ELSE CASE WHEN ROUND(subtotal - discount_amount, 2) > 0 '
            .'THEN ROUND(subtotal - discount_amount, 2) ELSE 0 END '
            .'END';
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function totalsFromRows(array $rows): array
    {
        $invoices = 0;
        $delivered = 0;
        $refused = 0;
        $codPending = 0;
        $net = 0.0;
        $returned = 0.0;
        $original = 0.0;
        $cod = 0.0;

        foreach ($rows as $row) {
            $invoices += (int) $row['invoices_count'];
            $delivered += (int) $row['delivered_count'];
            $refused += (int) $row['refused_count'];
            $codPending += (int) $row['cod_pending_count'];
            $net += (float) $row['net_merchandise'];
            $returned += (float) $row['returned_merchandise'];
            $original += (float) $row['original_merchandise'];
            $cod += (float) $row['cod_collected'];
        }

        return [
            'invoices_count' => $invoices,
            'delivered_count' => $delivered,
            'refused_count' => $refused,
            'cod_pending_count' => $codPending,
            'net_merchandise' => $this->money($net),
            'returned_merchandise' => $this->money($returned),
            'original_merchandise' => $this->money($original),
            'cod_collected' => $this->money($cod),
            'refuse_rate' => $invoices > 0 ? round(($refused / $invoices) * 100, 1) : 0.0,
        ];
    }

    private function money(mixed $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
