<?php

namespace App\Services\GeneralHouse\Payments;

use App\Models\GeneralHouse\GhCreditNote;
use App\Models\GeneralHouse\GhDebitAdjustment;
use App\Models\GeneralHouse\GhDriverCashCollection;
use App\Models\GeneralHouse\GhDriverCashSettlement;
use App\Models\GeneralHouse\GhDriverCashSettlementAllocation;
use App\Models\GeneralHouse\GhDriverCashSettlementReversal;
use App\Models\GeneralHouse\GhRefund;
use App\Models\User;
use App\Support\GeneralHouse\GhCreditNoteStatus;
use App\Support\GeneralHouse\GhDebitAdjustmentStatus;
use App\Support\GeneralHouse\GhMoney;
use App\Support\GeneralHouse\GhRefundStatus;
use App\Support\GeneralHouse\GhReportPeriod;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Paginated custody detail lists for one driver (manager dashboard drill-down).
 */
final class GhDriverCashActivityService
{
    /** Settlement form shows at most this many outstanding collections (oldest first). */
    public const OUTSTANDING_LIMIT = 200;

    private const ADJUSTMENT_LIMIT = 50;

    public function __construct(
        private readonly GhCashSettlementQueryService $settlement,
    ) {}

    /** @return LengthAwarePaginator<int, GhDriverCashCollection> with settled_amount */
    public function collections(User $driver, ?GhReportPeriod $period, int $perPage = 25, string $pageName = 'collections_page'): LengthAwarePaginator
    {
        $page = $this->settlement->withCollectionSettled(GhDriverCashCollection::query())
            ->with(['order:id,reference', 'order.invoice:id,order_id,number,status,payment_status', 'payment:id,reference,method,status'])
            ->where('gh_driver_cash_collections.driver_id', $driver->id)
            ->when($period, fn ($q) => $q->where('collected_at', '>=', $period->startUtc)->where('collected_at', '<', $period->endUtc))
            ->orderByDesc('collected_at')->orderByDesc('gh_driver_cash_collections.id')
            ->paginate(max(1, min(50, $perPage)), ['*'], $pageName)
            ->withQueryString();
        $this->settlement->annotateCollections($page->getCollection());

        return $page;
    }

    /** @return Collection<int, GhDriverCashCollection> collections with unsettled cash, oldest first, settled_amount set */
    public function outstandingCollections(User $driver): Collection
    {
        $settled = $this->settlement->settledForCollection('gh_driver_cash_collections.id');

        return $this->settlement->annotateCollections($this->settlement->withCollectionSettled(GhDriverCashCollection::query())
            ->with(['order:id,reference', 'order.invoice:id,order_id,number,payment_status'])
            ->where('gh_driver_cash_collections.driver_id', $driver->id)
            ->whereRaw('('.$settled->toSql().') < gh_driver_cash_collections.amount', $settled->getBindings())
            ->orderBy('collected_at')->orderBy('gh_driver_cash_collections.id')
            ->limit(self::OUTSTANDING_LIMIT)
            ->get());
    }

    /**
     * @param  array{driver_id?: ?int, manager_id?: ?int, state?: ?string}  $filters
     * @return LengthAwarePaginator<int, GhDriverCashSettlement>
     */
    public function settlements(array $filters, ?GhReportPeriod $period, int $perPage = 25, string $pageName = 'page'): LengthAwarePaginator
    {
        return GhDriverCashSettlement::query()
            ->with(['driver:id,name_ar,name_en', 'settler:id,name_ar,name_en', 'reversal'])
            ->withCount('allocations')
            ->withSum('allocations', 'amount')
            ->when($filters['driver_id'] ?? null, fn ($q, $id) => $q->where('driver_id', $id))
            ->when($filters['manager_id'] ?? null, fn ($q, $id) => $q->where('settled_by', $id))
            ->when(($filters['state'] ?? null) === 'active', fn ($q) => $q->whereDoesntHave('reversal'))
            ->when(($filters['state'] ?? null) === 'reversed', fn ($q) => $q->whereHas('reversal'))
            ->when($period, fn ($q) => $q->where('settled_at', '>=', $period->startUtc)->where('settled_at', '<', $period->endUtc))
            ->orderByDesc('settled_at')->orderByDesc('id')
            ->paginate(max(1, min(50, $perPage)), ['*'], $pageName)
            ->withQueryString();
    }

    /**
     * Custody adjustments (reversals, later allocations of earlier settlements) and read-only
     * invoice adjustments on orders where this driver collected cash. Invoice adjustments never change custody.
     *
     * @return array{reversals: Collection<int, mixed>, late_allocations: Collection<int, mixed>, credit_notes: Collection<int, mixed>, debit_adjustments: Collection<int, mixed>, refunds: Collection<int, mixed>}
     */
    public function adjustments(User $driver, GhReportPeriod $period): array
    {
        $in = fn ($q, string $col) => $q->where($col, '>=', $period->startUtc)->where($col, '<', $period->endUtc);
        $orderIds = GhDriverCashCollection::query()->where('driver_id', $driver->id)->select('order_id');

        return [
            'reversals' => $in(GhDriverCashSettlementReversal::query()->where('driver_id', $driver->id), 'reversed_at')
                ->with(['settlement:id,reference', 'reverser:id,name_ar,name_en'])->orderByDesc('reversed_at')->limit(self::ADJUSTMENT_LIMIT)->get(),
            'late_allocations' => $in(GhDriverCashSettlementAllocation::query()->where('gh_driver_cash_settlement_allocations.driver_id', $driver->id), 'allocated_at')
                ->join('gh_driver_cash_settlements as s', 's.id', '=', 'gh_driver_cash_settlement_allocations.settlement_id')
                ->whereColumn('gh_driver_cash_settlement_allocations.batch_key', '<>', 's.idempotency_key')
                ->select('gh_driver_cash_settlement_allocations.*')
                ->with(['settlement:id,reference', 'collection:id,reference,order_id', 'allocator:id,name_ar,name_en'])
                ->orderByDesc('allocated_at')->limit(self::ADJUSTMENT_LIMIT)->get(),
            'credit_notes' => $in(GhCreditNote::query()->whereIn('order_id', $orderIds)->where('status', GhCreditNoteStatus::ISSUED), 'issued_at')
                ->with('invoice:id,number')->orderByDesc('issued_at')->limit(self::ADJUSTMENT_LIMIT)->get(),
            'debit_adjustments' => $in(GhDebitAdjustment::query()->whereIn('order_id', $orderIds)->where('status', GhDebitAdjustmentStatus::ISSUED), 'issued_at')
                ->with('invoice:id,number')->orderByDesc('issued_at')->limit(self::ADJUSTMENT_LIMIT)->get(),
            'refunds' => $in(GhRefund::query()->where('status', GhRefundStatus::PAID_OUT)
                ->whereHas('invoice', fn ($q) => $q->whereIn('order_id', $orderIds)), 'paid_out_at')
                ->with('invoice:id,number')->orderByDesc('paid_out_at')->limit(self::ADJUSTMENT_LIMIT)->get(),
        ];
    }

    /**
     * Chronological custody ledger for the period with running balance.
     *
     * @return array{opening: string, closing: string, entries: LengthAwarePaginator<int, object>}
     */
    public function ledger(User $driver, GhReportPeriod $period, int $perPage = 50, string $pageName = 'ledger_page'): array
    {
        $ordered = fn () => DB::query()->fromSub($this->ledgerUnion((int) $driver->id, $period), 'l')
            ->orderBy('occurred_at')->orderBy('kind_order')->orderBy('source_id');
        $opening = $this->balanceBefore((int) $driver->id, $period->startUtc);
        $entries = $ordered()->paginate(max(1, min(100, $perPage)), ['*'], $pageName)->withQueryString();

        $offset = ($entries->currentPage() - 1) * $entries->perPage();
        $running = $offset === 0 ? $opening : GhMoney::of(GhMoney::add(
            $opening,
            (string) DB::query()->fromSub($ordered()->limit($offset), 'p')->sum('delta'),
            2,
        ));
        $entries->getCollection()->transform(function ($row) use (&$running) {
            $running = GhMoney::of(GhMoney::add($running, (string) $row->delta, 2));
            $row->delta = GhMoney::of((string) $row->delta);
            $row->balance_after = $running;
            $row->occurred_at = Carbon::parse((string) $row->occurred_at, 'UTC');

            return $row;
        });

        $closing = GhMoney::of(GhMoney::add($opening, (string) DB::query()->fromSub($this->ledgerUnion((int) $driver->id, $period), 't')->sum('delta'), 2));

        return ['opening' => $opening, 'closing' => $closing, 'entries' => $entries];
    }

    /** Custody outstanding at the end of the Dubai business date (activity up to that instant only). */
    public function outstandingAsOf(User $driver, string $date): string
    {
        $day = GhReportPeriod::fromInput(['preset' => GhReportPeriod::PRESET_CUSTOM, 'from' => $date, 'to' => $date]);

        return $this->balanceBefore((int) $driver->id, $day->endUtc);
    }

    /** Custody outstanding from all activity strictly before the instant. */
    public function balanceBefore(int $driverId, Carbon $cutoffUtc): string
    {
        $before = fn ($q, string $col) => GhMoney::of((string) $q->where('driver_id', $driverId)->where($col, '<', $cutoffUtc)->sum('amount'));
        $collected = $before(GhDriverCashCollection::query(), 'collected_at');
        $settled = $before(GhDriverCashSettlement::query(), 'settled_at');
        $reversed = $before(GhDriverCashSettlementReversal::query(), 'reversed_at');

        return GhMoney::of(GhMoney::add(GhMoney::sub($collected, $settled, 2), $reversed, 2));
    }

    private function ledgerUnion(int $driverId, GhReportPeriod $period): QueryBuilder
    {
        $window = fn (QueryBuilder $q, string $col) => $q->where('driver_id', $driverId)
            ->where($col, '>=', $period->startUtc)->where($col, '<', $period->endUtc);

        $collections = $window(DB::table('gh_driver_cash_collections'), 'collected_at')
            ->selectRaw("'COLLECTION' as kind, 1 as kind_order, id as source_id, reference, collected_at as occurred_at, amount as delta, order_id");
        $settlements = $window(DB::table('gh_driver_cash_settlements'), 'settled_at')
            ->selectRaw("'SETTLEMENT' as kind, 2 as kind_order, id as source_id, reference, settled_at as occurred_at, (0 - amount) as delta, NULL as order_id");
        $reversals = $window(DB::table('gh_driver_cash_settlement_reversals'), 'reversed_at')
            ->selectRaw("'REVERSAL' as kind, 3 as kind_order, settlement_id as source_id, reference, reversed_at as occurred_at, amount as delta, NULL as order_id");

        return $collections->unionAll($settlements)->unionAll($reversals);
    }
}
