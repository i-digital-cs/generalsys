<?php

namespace App\Services\GeneralHouse\Payments;

use App\Models\GeneralHouse\GhDriverCashBalance;
use App\Models\GeneralHouse\GhDriverCashCollection;
use App\Models\GeneralHouse\GhDriverCashSettlement;
use App\Models\GeneralHouse\GhDriverCashSettlementAllocation;
use App\Models\GeneralHouse\GhDriverCashSettlementReversal;
use App\Models\User;
use App\Support\GeneralHouse\GhMoney;
use App\Support\GeneralHouse\GhReportPeriod;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Manager custody dashboard figures. Totals are recomputed from source rows
 * (collections, settlements, reversals, allocations) and checked against the materialized balance.
 */
final class GhDriverCashDashboardService
{
    public function __construct(
        private readonly GhCashSettlementQueryService $settlement,
    ) {}

    /** @return Collection<int, User> all drivers (inactive included: they may still hold custody) */
    public function driverOptions(): Collection
    {
        return User::query()->where('role', User::ROLE_DRIVER)
            ->orderBy('name_en')->limit(500)
            ->get(['id', 'name_ar', 'name_en', 'status']);
    }

    /** @return Collection<int, User> managers who recorded at least one settlement */
    public function settlerOptions(): Collection
    {
        return User::query()
            ->whereIn('id', GhDriverCashSettlement::query()->select('settled_by')->distinct())
            ->orderBy('name_en')
            ->get(['id', 'name_ar', 'name_en']);
    }

    /** @return LengthAwarePaginator<int, User> drivers with balance columns */
    public function driversOverview(?string $search = null, int $perPage = 25): LengthAwarePaginator
    {
        return User::query()
            ->where('users.role', User::ROLE_DRIVER)
            ->leftJoin('gh_driver_cash_balances as b', 'b.driver_id', '=', 'users.id')
            ->when($search !== null && trim($search) !== '', function ($q) use ($search) {
                $term = '%'.trim((string) $search).'%';
                $q->where(fn ($w) => $w->where('users.name_en', 'like', $term)
                    ->orWhere('users.name_ar', 'like', $term)
                    ->orWhere('users.email', 'like', $term));
            })
            ->select(['users.id', 'users.name_ar', 'users.name_en', 'users.email', 'users.status'])
            ->selectRaw('COALESCE(b.collected_total, 0) as collected_total, COALESCE(b.settled_total, 0) as settled_total, COALESCE(b.outstanding, 0) as outstanding')
            ->orderByRaw('COALESCE(b.outstanding, 0) DESC')
            ->orderBy('users.name_en')
            ->paginate(max(1, min(50, $perPage)))
            ->withQueryString();
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(User $driver): array
    {
        $id = (int) $driver->id;
        $collected = $this->sum(GhDriverCashCollection::query()->where('driver_id', $id));
        $settledGross = $this->sum(GhDriverCashSettlement::query()->where('driver_id', $id));
        $reversed = $this->sum(GhDriverCashSettlementReversal::query()->where('driver_id', $id));
        $settledNet = GhMoney::of(GhMoney::sub($settledGross, $reversed, 2));
        $allocated = $this->sum(GhDriverCashSettlementAllocation::query()->effective()->where('driver_id', $id));
        $outstanding = GhMoney::of(GhMoney::sub($collected, $settledNet, 2));
        $balance = GhDriverCashBalance::query()->find($id);

        return [
            'collected' => $collected,
            'settled' => $settledNet,
            'reversed' => $reversed,
            'outstanding' => $outstanding,
            'allocated' => $allocated,
            'unallocated' => GhMoney::of(GhMoney::sub($settledNet, $allocated, 2)),
            'collections_remaining' => GhMoney::of(GhMoney::sub($collected, $allocated, 2)),
            'balance_outstanding' => GhMoney::of((string) ($balance?->outstanding ?? '0')),
            'reconciled' => $balance === null
                ? GhMoney::cmp($outstanding, '0') === 0
                : GhMoney::cmp((string) $balance->outstanding, $outstanding) === 0
                    && GhMoney::cmp((string) $balance->collected_total, $collected) === 0
                    && GhMoney::cmp((string) $balance->settled_total, $settledNet) === 0,
            'counts' => $this->settlement->statusCounts(GhDriverCashCollection::query()->where('gh_driver_cash_collections.driver_id', $id)),
            'last_collected_at' => $this->utc(GhDriverCashCollection::query()->where('driver_id', $id)->max('collected_at')),
            'last_settled_at' => $this->utc(GhDriverCashSettlement::query()->where('driver_id', $id)->whereDoesntHave('reversal')->max('settled_at')),
        ];
    }

    /** @return array{collected: string, settled: string, reversed: string} activity within the period */
    public function periodActivity(User $driver, GhReportPeriod $period): array
    {
        $id = (int) $driver->id;
        $between = fn ($q, string $col) => $q->where('driver_id', $id)->where($col, '>=', $period->startUtc)->where($col, '<', $period->endUtc);

        return [
            'collected' => $this->sum($between(GhDriverCashCollection::query(), 'collected_at')),
            'settled' => $this->sum($between(GhDriverCashSettlement::query(), 'settled_at')),
            'reversed' => $this->sum($between(GhDriverCashSettlementReversal::query(), 'reversed_at')),
        ];
    }

    private function sum($query): string
    {
        return GhMoney::of((string) $query->sum('amount'));
    }

    private function utc(mixed $raw): ?Carbon
    {
        return $raw === null ? null : Carbon::parse((string) $raw, 'UTC');
    }
}
