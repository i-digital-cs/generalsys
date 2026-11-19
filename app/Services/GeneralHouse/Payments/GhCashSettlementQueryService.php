<?php

namespace App\Services\GeneralHouse\Payments;

use App\Models\GeneralHouse\GhDriverCashCollection;
use App\Models\GeneralHouse\GhDriverCashSettlementAllocation;
use App\Support\GeneralHouse\GhCashSettlementStatus;
use App\Support\GeneralHouse\GhMoney;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Single source of truth for driver-cash settlement amounts and statuses
 * (collection, order and invoice level). Settled = effective allocations only (reversed settlements excluded).
 * Dashboards, lists, reports and exports are consumers.
 */
final class GhCashSettlementQueryService
{
    /** Correlated SUM of effective allocations for the collection row `$collectionIdColumn`. */
    public function settledForCollection(string $collectionIdColumn): QueryBuilder
    {
        return GhDriverCashSettlementAllocation::query()->effective()
            ->selectRaw('COALESCE(SUM(gh_driver_cash_settlement_allocations.amount), 0)')
            ->whereColumn('gh_driver_cash_settlement_allocations.collection_id', $collectionIdColumn)
            ->toBase();
    }

    /** Correlated SUM of driver cash collected for the order `$orderIdColumn`. */
    public function collectedForOrder(string $orderIdColumn): QueryBuilder
    {
        return DB::table('gh_driver_cash_collections as dcc_o')
            ->selectRaw('COALESCE(SUM(dcc_o.amount), 0)')
            ->whereColumn('dcc_o.order_id', $orderIdColumn);
    }

    /** Correlated SUM of effective allocations against collections of the order `$orderIdColumn`. */
    public function settledForOrder(string $orderIdColumn): QueryBuilder
    {
        return GhDriverCashSettlementAllocation::query()->effective()
            ->join('gh_driver_cash_collections as dcc_s', 'dcc_s.id', '=', 'gh_driver_cash_settlement_allocations.collection_id')
            ->selectRaw('COALESCE(SUM(gh_driver_cash_settlement_allocations.amount), 0)')
            ->whereColumn('dcc_s.order_id', $orderIdColumn)
            ->toBase();
    }

    /** Adds driver_cash_collected / driver_cash_settled columns to a query over a table with an order id column. */
    public function withOrderSettlementTotals(Builder $query, string $orderIdColumn): Builder
    {
        return $query
            ->selectSub($this->collectedForOrder($orderIdColumn), 'driver_cash_collected')
            ->selectSub($this->settledForOrder($orderIdColumn), 'driver_cash_settled');
    }

    public function filterBySettlementStatus(Builder $query, string $orderIdColumn, string $status): Builder
    {
        $collected = $this->collectedForOrder($orderIdColumn);
        $settled = $this->settledForOrder($orderIdColumn);
        $c = '('.$collected->toSql().')';
        $s = '('.$settled->toSql().')';
        $cb = $collected->getBindings();
        $sb = $settled->getBindings();

        return match ($status) {
            GhCashSettlementStatus::NOT_APPLICABLE => $query->whereRaw("{$c} <= 0", $cb),
            GhCashSettlementStatus::NOT_SETTLED => $query->whereRaw("{$c} > 0", $cb)->whereRaw("{$s} <= 0", $sb),
            GhCashSettlementStatus::PARTIAL => $query->whereRaw("{$s} > 0", $sb)->whereRaw("{$s} < {$c}", array_merge($sb, $cb)),
            GhCashSettlementStatus::SETTLED => $query->whereRaw("{$c} > 0", $cb)->whereRaw("{$s} >= {$c}", array_merge($sb, $cb)),
            default => $query,
        };
    }

    /** Adds settled_amount to a collections query. */
    public function withCollectionSettled(Builder $query): Builder
    {
        return $query->addSelect('gh_driver_cash_collections.*')
            ->selectSub($this->settledForCollection('gh_driver_cash_collections.id'), 'settled_amount');
    }

    /**
     * @param  Builder  $collections  filtered GhDriverCashCollection query
     * @return array{not_settled: int, partial: int, settled: int}
     */
    public function statusCounts(Builder $collections): array
    {
        $row = DB::query()->fromSub(
            $collections->select('gh_driver_cash_collections.amount')
                ->selectSub($this->settledForCollection('gh_driver_cash_collections.id'), 'settled')
                ->toBase(),
            't'
        )->selectRaw('
            SUM(CASE WHEN settled <= 0 THEN 1 ELSE 0 END) as not_settled,
            SUM(CASE WHEN settled > 0 AND settled < amount THEN 1 ELSE 0 END) as partial,
            SUM(CASE WHEN settled >= amount THEN 1 ELSE 0 END) as settled
        ')->first();

        return ['not_settled' => (int) ($row->not_settled ?? 0), 'partial' => (int) ($row->partial ?? 0), 'settled' => (int) ($row->settled ?? 0)];
    }

    /**
     * Normalizes rows loaded via withCollectionSettled(): sets settled_amount / remaining_amount / settlement_status.
     *
     * @param  Collection<int, GhDriverCashCollection>  $collections
     * @return Collection<int, GhDriverCashCollection>
     */
    public function annotateCollections(Collection $collections): Collection
    {
        return $collections->each(function (GhDriverCashCollection $c) {
            $settled = GhMoney::of((string) ($c->settled_amount ?? '0'));
            $c->setAttribute('settled_amount', $settled);
            $c->setAttribute('remaining_amount', GhMoney::of(GhMoney::sub((string) $c->amount, $settled, 2)));
            $c->setAttribute('settlement_status', GhCashSettlementStatus::derive((string) $c->amount, $settled));
        });
    }

    /**
     * @param  Collection<int, GhDriverCashCollection>  $collections  with allocations.settlement.reversal loaded
     * @return array{collected: string, settled: string, remaining: string, status: string, rows: list<array<string, mixed>>, settlements: Collection<int, mixed>, last_settled_at: mixed}
     */
    public function summarize(Collection $collections): array
    {
        $collected = '0.00';
        $settled = '0.00';
        $rows = [];
        $settlements = collect();
        foreach ($collections as $collection) {
            $effective = $collection->allocations->filter(fn ($a) => $a->settlement?->reversal === null);
            $rowSettled = $this->sumAmounts($effective);
            $collected = GhMoney::of(GhMoney::add($collected, (string) $collection->amount, 2));
            $settled = GhMoney::of(GhMoney::add($settled, $rowSettled, 2));
            $rows[] = [
                'collection' => $collection,
                'settled' => $rowSettled,
                'remaining' => GhMoney::of(GhMoney::sub((string) $collection->amount, $rowSettled, 2)),
                'status' => GhCashSettlementStatus::derive((string) $collection->amount, $rowSettled),
            ];
            $settlements = $settlements->merge($collection->allocations->pluck('settlement')->filter());
        }
        $settlements = $settlements->unique('id')->sortBy('settled_at')->values();
        $active = $settlements->filter(fn ($s) => $s->reversal === null);

        return [
            'collected' => $collected,
            'settled' => $settled,
            'remaining' => GhMoney::of(GhMoney::sub($collected, $settled, 2)),
            'status' => GhCashSettlementStatus::derive($collected, $settled),
            'rows' => $rows,
            'settlements' => $settlements,
            'last_settled_at' => $active->max('settled_at'),
        ];
    }

    /** @param  Collection<int, GhDriverCashSettlementAllocation>  $allocations */
    private function sumAmounts(Collection $allocations): string
    {
        $sum = '0.00';
        foreach ($allocations as $allocation) {
            $sum = GhMoney::of(GhMoney::add($sum, (string) $allocation->amount, 2));
        }

        return $sum;
    }
}
