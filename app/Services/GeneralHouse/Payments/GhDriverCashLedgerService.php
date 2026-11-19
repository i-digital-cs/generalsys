<?php

namespace App\Services\GeneralHouse\Payments;

use App\Models\GeneralHouse\GhDriverCashBalance;
use App\Models\GeneralHouse\GhDriverCashEvent;
use App\Models\User;
use App\Support\GeneralHouse\GhMoney;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Materialized driver custody balance + custody audit events.
 * Callers must already be inside a DB transaction; the balance row lock serializes every
 * custody write for one driver (collections, settlements, allocations, reversals).
 */
final class GhDriverCashLedgerService
{
    public function lockBalance(int $driverId): GhDriverCashBalance
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

    /** Settled delta may be negative (reversal). Rejects any result below zero outstanding. */
    public function applyBalanceDelta(int $driverId, string $collectedDelta, string $settledDelta): GhDriverCashBalance
    {
        $balance = $this->lockBalance($driverId);
        $collected = GhMoney::of(GhMoney::add((string) $balance->collected_total, $collectedDelta, 2));
        $settled = GhMoney::of(GhMoney::add((string) $balance->settled_total, $settledDelta, 2));
        $outstanding = GhMoney::of(GhMoney::sub($collected, $settled, 2));
        if (GhMoney::cmp($outstanding, '0') < 0 || GhMoney::cmp($settled, '0') < 0) {
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

        return $balance;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function recordEvent(
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
}
