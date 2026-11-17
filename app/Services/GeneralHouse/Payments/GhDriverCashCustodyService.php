<?php

namespace App\Services\GeneralHouse\Payments;

use App\Models\GeneralHouse\GhDriverCashBalance;
use App\Models\GeneralHouse\GhDriverCashCollection;
use App\Models\GeneralHouse\GhDriverCashEvent;
use App\Models\GeneralHouse\GhDriverCashSettlement;
use App\Models\GeneralHouse\GhOrder;
use App\Models\GeneralHouse\GhPayment;
use App\Models\User;
use App\Support\GeneralHouse\GhCatalogAccess;
use App\Support\GeneralHouse\GhMoney;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Physical driver cash custody ledger.
 * Collections increase outstanding; manager settlements decrease it.
 * Settlements are NOT customer payments and must not affect invoice AR or revenue KPIs.
 */
final class GhDriverCashCustodyService
{
    public function __construct(
        private readonly GhDriverCashNumberService $numbers,
    ) {}

    /**
     * Record actual cash collected by a driver at delivery (idempotent per order).
     *
     * @param  array{
     *   amount: string|float|int,
     *   payment_id?: int|null,
     *   notes?: string|null,
     *   occurred_at?: string|Carbon|null,
     *   idempotency_key?: string|null,
     * }  $data
     */
    public function recordDeliveryCashCollection(GhOrder $order, User $driver, array $data): GhDriverCashCollection
    {
        $amount = GhMoney::of($data['amount'] ?? '0');
        if (GhMoney::cmp($amount, '0') <= 0) {
            throw ValidationException::withMessages([
                'cash_collected_amount' => [__('gh.errors.cash_collected_amount_required')],
            ]);
        }

        $key = trim((string) ($data['idempotency_key'] ?? ''));
        if ($key === '') {
            $key = $this->collectionKeyForOrder($order);
        }

        $existing = GhDriverCashCollection::query()->where('idempotency_key', $key)->first();
        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($order, $driver, $data, $amount, $key) {
            $dup = GhDriverCashCollection::query()
                ->where('idempotency_key', $key)
                ->lockForUpdate()
                ->first();
            if ($dup !== null) {
                return $dup;
            }

            $occurred = $this->parseOccurredAt($data['occurred_at'] ?? null);
            $collection = new GhDriverCashCollection;
            $collection->forceFill([
                'reference' => $this->numbers->nextCollectionReference(),
                'driver_id' => $driver->id,
                'order_id' => $order->id,
                'payment_id' => $data['payment_id'] ?? null,
                'amount' => $amount,
                'currency' => 'AED',
                'source' => 'DRIVER_DELIVERY',
                'collected_at' => $occurred->utc(),
                'recorded_by' => $driver->id,
                'notes' => $data['notes'] ?? null,
                'idempotency_key' => $key,
            ])->save();

            $this->applyBalanceDelta($driver->id, $amount, '0.00');
            $this->recordEvent(
                $driver->id,
                'COLLECTION_RECORDED',
                $driver,
                $collection->id,
                null,
                [
                    'reference' => $collection->reference,
                    'order_id' => $order->id,
                    'amount' => $amount,
                    'payment_id' => $collection->payment_id,
                ],
                $occurred,
            );

            return $collection->fresh();
        });
    }

    /**
     * Manager records a partial/full cash handoff from a driver.
     *
     * @param  array{
     *   amount: string|float|int,
     *   notes?: string|null,
     *   receipt_reference?: string|null,
     *   idempotency_key?: string|null,
     *   occurred_at?: string|Carbon|null,
     * }  $data
     */
    public function recordSettlement(User $driver, User $manager, array $data): GhDriverCashSettlement
    {
        if (! GhCatalogAccess::mayManage($manager)) {
            throw ValidationException::withMessages([
                'settlement' => [__('gh.errors.cash_settlement_forbidden')],
            ]);
        }

        if ((int) $manager->id === (int) $driver->id) {
            throw ValidationException::withMessages([
                'settlement' => [__('gh.errors.cash_settlement_self_forbidden')],
            ]);
        }

        $amount = GhMoney::of($data['amount'] ?? '0');
        if (GhMoney::cmp($amount, '0') <= 0) {
            throw ValidationException::withMessages([
                'amount' => [__('gh.errors.cash_settlement_amount_required')],
            ]);
        }

        $key = trim((string) ($data['idempotency_key'] ?? ''));
        if ($key === '') {
            throw ValidationException::withMessages([
                'idempotency_key' => [__('gh.errors.cash_settlement_idempotency_required')],
            ]);
        }

        $existing = GhDriverCashSettlement::query()->where('idempotency_key', $key)->first();
        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($driver, $manager, $data, $amount, $key) {
            $dup = GhDriverCashSettlement::query()
                ->where('idempotency_key', $key)
                ->lockForUpdate()
                ->first();
            if ($dup !== null) {
                return $dup;
            }

            $balance = $this->lockBalance($driver->id);
            $outstanding = GhMoney::of((string) $balance->outstanding);
            if (GhMoney::cmp($amount, $outstanding) > 0) {
                throw ValidationException::withMessages([
                    'amount' => [__('gh.errors.cash_settlement_exceeds_outstanding', [
                        'outstanding' => $outstanding,
                    ])],
                ]);
            }

            $occurred = $this->parseOccurredAt($data['occurred_at'] ?? null);
            $settlement = new GhDriverCashSettlement;
            $settlement->forceFill([
                'reference' => $this->numbers->nextSettlementReference(),
                'driver_id' => $driver->id,
                'amount' => $amount,
                'currency' => 'AED',
                'settled_at' => $occurred->utc(),
                'settled_by' => $manager->id,
                'receipt_reference' => isset($data['receipt_reference'])
                    ? trim((string) $data['receipt_reference']) ?: null
                    : null,
                'notes' => isset($data['notes']) ? trim((string) $data['notes']) ?: null : null,
                'idempotency_key' => $key,
            ])->save();

            $this->applyBalanceDelta($driver->id, '0.00', $amount);
            $this->recordEvent(
                $driver->id,
                'SETTLEMENT_RECORDED',
                $manager,
                null,
                $settlement->id,
                [
                    'reference' => $settlement->reference,
                    'amount' => $amount,
                    'outstanding_after' => GhMoney::of((string) $this->lockBalance($driver->id)->outstanding),
                ],
                $occurred,
            );

            return $settlement->fresh();
        });
    }

    public function outstandingFor(User $driver): string
    {
        $row = GhDriverCashBalance::query()->find($driver->id);

        return GhMoney::of((string) ($row?->outstanding ?? '0'));
    }

    /**
     * @return array{driver_id: int, collected_total: string, settled_total: string, outstanding: string}
     */
    public function snapshotFor(User $driver): array
    {
        $row = GhDriverCashBalance::query()->find($driver->id);

        return [
            'driver_id' => $driver->id,
            'collected_total' => GhMoney::of((string) ($row?->collected_total ?? '0')),
            'settled_total' => GhMoney::of((string) ($row?->settled_total ?? '0')),
            'outstanding' => GhMoney::of((string) ($row?->outstanding ?? '0')),
        ];
    }

    /**
     * @return Collection<int, array{driver: User, collected_total: string, settled_total: string, outstanding: string}>
     */
    public function outstandingDriversReport(): Collection
    {
        return GhDriverCashBalance::query()
            ->with('driver')
            ->where('outstanding', '>', 0)
            ->orderByDesc('outstanding')
            ->get()
            ->map(fn (GhDriverCashBalance $b) => [
                'driver' => $b->driver,
                'collected_total' => GhMoney::of((string) $b->collected_total),
                'settled_total' => GhMoney::of((string) $b->settled_total),
                'outstanding' => GhMoney::of((string) $b->outstanding),
            ]);
    }

    /**
     * @return LengthAwarePaginator<int, GhDriverCashCollection>
     */
    public function paginateCollections(?int $driverId = null, int $perPage = 25): LengthAwarePaginator
    {
        $perPage = max(1, min(50, $perPage));

        return GhDriverCashCollection::query()
            ->with(['driver', 'order', 'payment'])
            ->when($driverId, fn ($q) => $q->where('driver_id', $driverId))
            ->orderByDesc('collected_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @return LengthAwarePaginator<int, GhDriverCashSettlement>
     */
    public function paginateSettlements(?int $driverId = null, int $perPage = 25): LengthAwarePaginator
    {
        $perPage = max(1, min(50, $perPage));

        return GhDriverCashSettlement::query()
            ->with(['driver', 'settler'])
            ->when($driverId, fn ($q) => $q->where('driver_id', $driverId))
            ->orderByDesc('settled_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function collectionKeyForOrder(GhOrder $order): string
    {
        return 'gh-dcc-order-'.$order->id;
    }

    public function attachPaymentIfMissing(GhDriverCashCollection $collection, GhPayment $payment): void
    {
        if ($collection->payment_id !== null) {
            return;
        }

        $collection->forceFill(['payment_id' => $payment->id])->save();
    }

    private function applyBalanceDelta(int $driverId, string $collectedDelta, string $settledDelta): void
    {
        $balance = $this->lockBalance($driverId);
        $collected = GhMoney::of(GhMoney::add((string) $balance->collected_total, $collectedDelta, 2));
        $settled = GhMoney::of(GhMoney::add((string) $balance->settled_total, $settledDelta, 2));
        $outstanding = GhMoney::of(GhMoney::sub($collected, $settled, 2));
        if (GhMoney::cmp($outstanding, '0') < 0) {
            throw ValidationException::withMessages([
                'amount' => [__('gh.errors.cash_custody_negative_blocked')],
            ]);
        }

        $balance->forceFill([
            'collected_total' => $collected,
            'settled_total' => $settled,
            'outstanding' => $outstanding,
            'updated_at' => now('UTC'),
        ])->save();
    }

    private function lockBalance(int $driverId): GhDriverCashBalance
    {
        $row = GhDriverCashBalance::query()->whereKey($driverId)->lockForUpdate()->first();
        if ($row !== null) {
            return $row;
        }

        $created = new GhDriverCashBalance;
        $created->forceFill([
            'driver_id' => $driverId,
            'collected_total' => '0.00',
            'settled_total' => '0.00',
            'outstanding' => '0.00',
            'updated_at' => now('UTC'),
        ])->save();

        return GhDriverCashBalance::query()->whereKey($driverId)->lockForUpdate()->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function recordEvent(
        int $driverId,
        string $type,
        ?User $actor,
        ?int $collectionId,
        ?int $settlementId,
        array $payload,
        Carbon $occurredAt,
    ): void {
        $event = new GhDriverCashEvent;
        $event->forceFill([
            'driver_id' => $driverId,
            'event_type' => $type,
            'collection_id' => $collectionId,
            'settlement_id' => $settlementId,
            'actor_id' => $actor?->id,
            'occurred_at' => $occurredAt->copy()->utc(),
            'payload' => $payload,
        ])->save();
    }

    private function parseOccurredAt(mixed $raw): Carbon
    {
        if ($raw instanceof Carbon) {
            return $raw->copy();
        }
        if (is_string($raw) && trim($raw) !== '') {
            try {
                return Carbon::parse($raw);
            } catch (\Throwable) {
                // fall through
            }
        }

        return Carbon::now('UTC');
    }
}
