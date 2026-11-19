<?php

namespace App\Services\GeneralHouse\Payments;

use App\Models\GeneralHouse\GhDriverCashCollection;
use App\Models\GeneralHouse\GhDriverCashSettlement;
use App\Models\GeneralHouse\GhDriverCashSettlementAllocation;
use App\Models\User;
use App\Support\GeneralHouse\GhMoney;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Manager-chosen allocation of settlement cash to specific collections (no FIFO, no auto-split).
 * lockAndValidate() must run inside the caller's transaction, after the driver balance lock.
 */
final class GhDriverCashAllocator
{
    /**
     * @param  mixed  $raw  list<array{collection_id: int|string, amount: string|float|int}>
     * @return array<int, string> collection_id => amount
     */
    public function normalize(mixed $raw): array
    {
        if (! is_array($raw) || $raw === []) {
            throw ValidationException::withMessages([
                'allocations' => [__('gh.errors.cash_settlement_allocations_required')],
            ]);
        }

        $out = [];
        foreach ($raw as $row) {
            $id = (int) ($row['collection_id'] ?? 0);
            $amount = GhMoney::of($row['amount'] ?? '0');
            if ($id <= 0) {
                throw ValidationException::withMessages([
                    'allocations' => [__('gh.errors.cash_settlement_collection_invalid')],
                ]);
            }
            if (isset($out[$id])) {
                throw ValidationException::withMessages([
                    'allocations.'.$id.'.amount' => [__('gh.errors.cash_settlement_allocation_duplicate')],
                ]);
            }
            if (GhMoney::cmp($amount, '0') <= 0) {
                throw ValidationException::withMessages([
                    'allocations.'.$id.'.amount' => [__('gh.errors.cash_settlement_allocation_amount_invalid')],
                ]);
            }
            $out[$id] = $amount;
        }

        return $out;
    }

    /** @param  array<int, string>  $allocations */
    public function total(array $allocations): string
    {
        $sum = '0.00';
        foreach ($allocations as $amount) {
            $sum = GhMoney::of(GhMoney::add($sum, $amount, 2));
        }

        return $sum;
    }

    /**
     * Locks the chosen collections of this driver and checks each amount against what is still unsettled.
     *
     * @param  array<int, string>  $allocations
     * @return Collection<int, GhDriverCashCollection> keyed by id
     */
    public function lockAndValidate(int $driverId, array $allocations): Collection
    {
        $collections = GhDriverCashCollection::query()
            ->whereIn('id', array_keys($allocations))
            ->where('driver_id', $driverId)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($allocations as $id => $amount) {
            $collection = $collections->get($id);
            if ($collection === null) {
                throw ValidationException::withMessages([
                    'allocations.'.$id.'.amount' => [__('gh.errors.cash_settlement_collection_invalid')],
                ]);
            }
            $remaining = $this->remainingFor($collection);
            if (GhMoney::cmp($amount, $remaining) > 0) {
                throw ValidationException::withMessages([
                    'allocations.'.$id.'.amount' => [__('gh.errors.cash_settlement_allocation_exceeds', [
                        'reference' => $collection->reference,
                        'remaining' => $remaining,
                    ])],
                ]);
            }
        }

        return $collections;
    }

    public function remainingFor(GhDriverCashCollection $collection): string
    {
        $settled = GhMoney::of((string) GhDriverCashSettlementAllocation::query()
            ->effective()
            ->where('collection_id', $collection->id)
            ->sum('amount'));

        return GhMoney::of(GhMoney::sub((string) $collection->amount, $settled, 2));
    }

    /**
     * @param  array<int, string>  $allocations
     * @param  Collection<int, GhDriverCashCollection>  $collections
     * @return list<array{collection_id: int, reference: string, order_id: int, amount: string}>
     */
    public function write(
        GhDriverCashSettlement $settlement,
        array $allocations,
        Collection $collections,
        User $actor,
        string $batchKey,
        Carbon $now,
    ): array {
        $written = [];
        foreach ($allocations as $id => $amount) {
            $row = new GhDriverCashSettlementAllocation;
            $row->forceFill([
                'settlement_id' => $settlement->id,
                'collection_id' => $id,
                'driver_id' => $settlement->driver_id,
                'amount' => $amount,
                'currency' => 'AED',
                'batch_key' => $batchKey,
                'allocated_by' => $actor->id,
                'allocated_at' => $now->copy()->utc(),
            ])->save();
            $collection = $collections->get($id);
            $written[] = [
                'collection_id' => $id,
                'reference' => (string) $collection->reference,
                'order_id' => (int) $collection->order_id,
                'amount' => $amount,
            ];
        }

        return $written;
    }
}
