<?php

namespace App\Services\GeneralHouse\Reporting;

use App\Models\GeneralHouse\GhDriverCashBalance;
use App\Models\GeneralHouse\GhDriverCashCollection;
use App\Models\GeneralHouse\GhDriverCashSettlement;
use App\Models\GeneralHouse\GhDriverCashSettlementReversal;
use App\Models\User;
use App\Services\GeneralHouse\Payments\GhCashSettlementQueryService;
use App\Support\GeneralHouse\GhMoney;
use App\Support\GeneralHouse\GhReportPeriod;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Driver cash reconciliation report (Manager). Settlement figures come from GhCashSettlementQueryService.
 */
final class GhDriverCashReportService
{
    public const EXPORT_LIMIT = 5000;

    public function __construct(
        private readonly GhCashSettlementQueryService $settlement,
    ) {}

    /** @return array<string, mixed> */
    public function totals(GhReportPeriod $period, ?int $driverId): array
    {
        $in = fn ($q, string $col) => $q->where($col, '>=', $period->startUtc)->where($col, '<', $period->endUtc)
            ->when($driverId, fn ($w) => $w->where('driver_id', $driverId));
        $sum = fn ($q) => GhMoney::of((string) $q->sum('amount'));

        return [
            'collected' => $sum($in(GhDriverCashCollection::query(), 'collected_at')),
            'settled' => $sum($in(GhDriverCashSettlement::query(), 'settled_at')),
            'reversed' => $sum($in(GhDriverCashSettlementReversal::query(), 'reversed_at')),
            'outstanding_now' => GhMoney::of((string) GhDriverCashBalance::query()
                ->when($driverId, fn ($q) => $q->where('driver_id', $driverId))->sum('outstanding')),
            'counts' => $this->statusCounts($period, $driverId),
        ];
    }

    /** @return LengthAwarePaginator<int, GhDriverCashCollection> */
    public function outstandingInvoices(?int $driverId, int $perPage = 25): LengthAwarePaginator
    {
        $page = $this->outstandingQuery($driverId)
            ->paginate(max(1, min(50, $perPage)), ['*'], 'outstanding_page')
            ->withQueryString();
        $this->settlement->annotateCollections($page->getCollection());

        return $page;
    }

    /** @return Collection<int, GhDriverCashCollection> */
    public function outstandingInvoicesForExport(?int $driverId): Collection
    {
        return $this->settlement->annotateCollections($this->outstandingQuery($driverId)->limit(self::EXPORT_LIMIT)->get());
    }

    /** @return Collection<int, object> drivers with outstanding custody, highest first */
    public function byDriver(?int $driverId): Collection
    {
        return User::query()
            ->join('gh_driver_cash_balances as b', 'b.driver_id', '=', 'users.id')
            ->where('users.role', User::ROLE_DRIVER)
            ->when($driverId, fn ($q) => $q->where('users.id', $driverId))
            ->select(['users.id', 'users.name_ar', 'users.name_en', 'b.collected_total', 'b.settled_total', 'b.outstanding'])
            ->orderByDesc('b.outstanding')
            ->limit(self::EXPORT_LIMIT)
            ->get();
    }

    /** @return list<array{date: string, collected: string, settled: string, reversed: string}> Dubai business dates */
    public function byDate(GhReportPeriod $period, ?int $driverId): array
    {
        $series = [
            'collected' => $this->dailySums('gh_driver_cash_collections', 'collected_at', $period, $driverId),
            'settled' => $this->dailySums('gh_driver_cash_settlements', 'settled_at', $period, $driverId),
            'reversed' => $this->dailySums('gh_driver_cash_settlement_reversals', 'reversed_at', $period, $driverId),
        ];
        $dates = collect($series)->flatMap(fn ($rows) => array_keys($rows))->unique()->sort()->values();

        return $dates->map(fn (string $d) => [
            'date' => $d,
            'collected' => GhMoney::of((string) ($series['collected'][$d] ?? '0')),
            'settled' => GhMoney::of((string) ($series['settled'][$d] ?? '0')),
            'reversed' => GhMoney::of((string) ($series['reversed'][$d] ?? '0')),
        ])->all();
    }

    /** @return list<array{manager: ?User, settled_count: int, settled: string, reversed_count: int, reversed: string}> */
    public function byManager(GhReportPeriod $period, ?int $driverId): array
    {
        $grouped = fn (string $table, string $actor, string $col) => DB::table($table)
            ->where($col, '>=', $period->startUtc)->where($col, '<', $period->endUtc)
            ->when($driverId, fn ($q) => $q->where('driver_id', $driverId))
            ->groupBy($actor)
            ->selectRaw("{$actor} as actor_id, COUNT(*) as n, COALESCE(SUM(amount), 0) as total")
            ->get()->keyBy('actor_id');
        $settled = $grouped('gh_driver_cash_settlements', 'settled_by', 'settled_at');
        $reversed = $grouped('gh_driver_cash_settlement_reversals', 'reversed_by', 'reversed_at');
        $ids = $settled->keys()->merge($reversed->keys())->unique()->values();
        $users = User::query()->whereIn('id', $ids)->get(['id', 'name_ar', 'name_en'])->keyBy('id');

        return $ids->map(fn ($id) => [
            'manager' => $users->get($id),
            'settled_count' => (int) ($settled[$id]->n ?? 0),
            'settled' => GhMoney::of((string) ($settled[$id]->total ?? '0')),
            'reversed_count' => (int) ($reversed[$id]->n ?? 0),
            'reversed' => GhMoney::of((string) ($reversed[$id]->total ?? '0')),
        ])->sortByDesc('settled')->values()->all();
    }

    private function outstandingQuery(?int $driverId): Builder
    {
        $settled = $this->settlement->settledForCollection('gh_driver_cash_collections.id');

        return $this->settlement->withCollectionSettled(GhDriverCashCollection::query())
            ->with(['driver:id,name_ar,name_en', 'order:id,reference', 'order.invoice:id,order_id,number,customer_name_ar,customer_name_en'])
            ->when($driverId, fn ($q) => $q->where('gh_driver_cash_collections.driver_id', $driverId))
            ->whereRaw('('.$settled->toSql().') < gh_driver_cash_collections.amount', $settled->getBindings())
            ->orderBy('collected_at')->orderBy('gh_driver_cash_collections.id');
    }

    /** @return array{not_settled: int, partial: int, settled: int} collections collected in the period */
    private function statusCounts(GhReportPeriod $period, ?int $driverId): array
    {
        return $this->settlement->statusCounts(GhDriverCashCollection::query()
            ->where('collected_at', '>=', $period->startUtc)->where('collected_at', '<', $period->endUtc)
            ->when($driverId, fn ($q) => $q->where('gh_driver_cash_collections.driver_id', $driverId)));
    }

    /** @return array<string, string> local date => sum */
    private function dailySums(string $table, string $column, GhReportPeriod $period, ?int $driverId): array
    {
        $day = $this->localDateSql($column, $period->timezone);

        return DB::table($table)
            ->where($column, '>=', $period->startUtc)->where($column, '<', $period->endUtc)
            ->when($driverId, fn ($q) => $q->where('driver_id', $driverId))
            ->groupByRaw($day)
            ->selectRaw("{$day} as d, SUM(amount) as total")
            ->pluck('total', 'd')
            ->map(fn ($v) => (string) $v)
            ->all();
    }

    /**
     * SQL-side grouping by business date needs a driver-specific expression. The GH business timezone
     * (Asia/Dubai) has no DST, so a fixed offset is exact. Column names are internal constants, never input.
     */
    private function localDateSql(string $column, string $timezone): string
    {
        $minutes = Carbon::now($timezone)->utcOffset();
        if (DB::connection()->getDriverName() === 'sqlite') {
            return sprintf("date(datetime(%s, '%+d minutes'))", $column, $minutes);
        }

        return sprintf('DATE(DATE_ADD(%s, INTERVAL %d MINUTE))', $column, $minutes);
    }
}
