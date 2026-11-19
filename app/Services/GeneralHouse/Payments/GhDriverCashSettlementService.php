<?php

namespace App\Services\GeneralHouse\Payments;

use App\Models\GeneralHouse\GhDriverCashSettlement;
use App\Models\GeneralHouse\GhDriverCashSettlementAllocation;
use App\Models\GeneralHouse\GhDriverCashSettlementReversal;
use App\Models\User;
use App\Support\GeneralHouse\GhCatalogAccess;
use App\Support\GeneralHouse\GhMoney;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Manager settlement of driver cash custody (driver → company handover).
 *
 * Owner rule (2026-11-18): the manager chooses exactly which collections and amounts are settled;
 * allocations must equal the cash received. Settlements are not customer payments: invoices,
 * gh_payments and revenue are never touched. Confirmed settlements are immutable; corrections are
 * compensating reversals. Lock order everywhere: driver balance → settlement → collections.
 */
final class GhDriverCashSettlementService
{
    public const REASON_MAX_LENGTH = 500;

    public function __construct(
        private readonly GhDriverCashNumberService $numbers,
        private readonly GhDriverCashLedgerService $ledger,
        private readonly GhDriverCashAllocator $allocator,
    ) {}

    /**
     * @param  array{received_amount: mixed, allocations: mixed, idempotency_key: string, receipt_reference?: ?string, notes?: ?string}  $data
     */
    public function settle(User $driver, User $manager, array $data): GhDriverCashSettlement
    {
        $this->authorizeActor($driver, $manager);
        $received = GhMoney::of($data['received_amount'] ?? '0');
        if (GhMoney::cmp($received, '0') <= 0) {
            throw ValidationException::withMessages(['received_amount' => [__('gh.errors.cash_settlement_received_required')]]);
        }
        $allocations = $this->allocator->normalize($data['allocations'] ?? null);
        $allocated = $this->allocator->total($allocations);
        if (GhMoney::cmp($allocated, $received) !== 0) {
            throw ValidationException::withMessages(['received_amount' => [__('gh.errors.cash_settlement_total_mismatch', [
                'allocated' => $allocated, 'received' => $received,
            ])]]);
        }
        $key = $this->requireKey($data['idempotency_key'] ?? null);

        return GhDriverCashSettlement::query()->where('idempotency_key', $key)->first()
            ?? DB::transaction(fn () => $this->createSettlement($driver, $manager, $received, $allocations, $key, $data));
    }

    /**
     * Manager allocates the still-unallocated part of a settlement recorded before allocation existed.
     * Cash was already counted when that settlement was recorded, so the custody balance does not change.
     *
     * @param  array{allocations: mixed, idempotency_key: string}  $data
     */
    public function allocateUnallocated(GhDriverCashSettlement $settlement, User $manager, array $data): GhDriverCashSettlement
    {
        $driver = User::query()->findOrFail($settlement->driver_id);
        $this->authorizeActor($driver, $manager);
        $allocations = $this->allocator->normalize($data['allocations'] ?? null);
        $key = $this->requireKey($data['idempotency_key'] ?? null);

        return DB::transaction(function () use ($settlement, $manager, $allocations, $key) {
            $this->ledger->lockBalance($settlement->driver_id);
            $locked = GhDriverCashSettlement::query()->whereKey($settlement->id)->lockForUpdate()->firstOrFail();
            if (GhDriverCashSettlementAllocation::query()->where('settlement_id', $locked->id)->where('batch_key', $key)->exists()) {
                return $locked;
            }
            if ($locked->reversal()->exists()) {
                throw ValidationException::withMessages(['allocations' => [__('gh.errors.cash_settlement_reversed_cannot_allocate')]]);
            }
            $unallocated = $this->unallocatedFor($locked);
            if (GhMoney::cmp($this->allocator->total($allocations), $unallocated) > 0) {
                throw ValidationException::withMessages(['allocations' => [__('gh.errors.cash_settlement_unallocated_exceeded', [
                    'unallocated' => $unallocated,
                ])]]);
            }
            $collections = $this->allocator->lockAndValidate($locked->driver_id, $allocations);
            $now = Carbon::now('UTC');
            $written = $this->allocator->write($locked, $allocations, $collections, $manager, $key, $now);
            $this->ledger->recordEvent($locked->driver_id, 'SETTLEMENT_ALLOCATED', $manager, null, $locked->id, [
                'reference' => $locked->reference,
                'allocations' => $written,
                'unallocated_after' => $this->unallocatedFor($locked),
            ], $now);

            return $locked->fresh(['allocations']);
        });
    }

    /**
     * @param  array{reason: mixed, idempotency_key: string}  $data
     */
    public function reverse(GhDriverCashSettlement $settlement, User $manager, array $data): GhDriverCashSettlementReversal
    {
        $driver = User::query()->findOrFail($settlement->driver_id);
        $this->authorizeActor($driver, $manager);
        $reason = trim((string) ($data['reason'] ?? ''));
        if ($reason === '' || mb_strlen($reason) > self::REASON_MAX_LENGTH) {
            throw ValidationException::withMessages(['reason' => [__('gh.errors.cash_settlement_reversal_reason_required')]]);
        }
        $key = $this->requireKey($data['idempotency_key'] ?? null);

        return GhDriverCashSettlementReversal::query()->where('idempotency_key', $key)->first()
            ?? DB::transaction(fn () => $this->createReversal($settlement, $manager, $reason, $key));
    }

    public function unallocatedFor(GhDriverCashSettlement $settlement): string
    {
        $allocated = (string) GhDriverCashSettlementAllocation::query()->where('settlement_id', $settlement->id)->sum('amount');

        return GhMoney::of(GhMoney::sub((string) $settlement->amount, $allocated, 2));
    }

    public function mayAllocate(GhDriverCashSettlement $settlement): bool
    {
        return $settlement->reversal === null && GhMoney::cmp($this->unallocatedFor($settlement), '0') > 0;
    }

    public function mayReverse(GhDriverCashSettlement $settlement, User $actor): bool
    {
        return $settlement->reversal === null
            && GhCatalogAccess::mayManage($actor)
            && (int) $actor->id !== (int) $settlement->driver_id;
    }

    /**
     * @param  array<int, string>  $allocations
     * @param  array<string, mixed>  $data
     */
    private function createSettlement(User $driver, User $manager, string $received, array $allocations, string $key, array $data): GhDriverCashSettlement
    {
        $balance = $this->ledger->lockBalance($driver->id);
        $dup = GhDriverCashSettlement::query()->where('idempotency_key', $key)->first();
        if ($dup !== null) {
            return $dup;
        }
        $this->assertWithinOutstanding($driver->id, $received, GhMoney::of((string) $balance->outstanding));
        $collections = $this->allocator->lockAndValidate($driver->id, $allocations);

        $now = Carbon::now('UTC');
        $settlement = new GhDriverCashSettlement;
        $settlement->forceFill([
            'reference' => $this->numbers->nextSettlementReference(),
            'driver_id' => $driver->id,
            'amount' => $received,
            'currency' => 'AED',
            'settled_at' => $now,
            'settled_by' => $manager->id,
            'receipt_reference' => trim((string) ($data['receipt_reference'] ?? '')) ?: null,
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
            'idempotency_key' => $key,
        ])->save();

        $written = $this->allocator->write($settlement, $allocations, $collections, $manager, $key, $now);
        $after = $this->ledger->applyBalanceDelta($driver->id, '0.00', $received);
        $this->ledger->recordEvent($driver->id, 'SETTLEMENT_RECORDED', $manager, null, $settlement->id, [
            'reference' => $settlement->reference,
            'amount' => $received,
            'allocations' => $written,
            'outstanding_after' => GhMoney::of((string) $after->outstanding),
        ], $now);

        return $settlement->fresh(['allocations']);
    }

    private function createReversal(GhDriverCashSettlement $settlement, User $manager, string $reason, string $key): GhDriverCashSettlementReversal
    {
        $this->ledger->lockBalance($settlement->driver_id);
        $dup = GhDriverCashSettlementReversal::query()->where('idempotency_key', $key)->first();
        if ($dup !== null) {
            return $dup;
        }
        $locked = GhDriverCashSettlement::query()->whereKey($settlement->id)->lockForUpdate()->firstOrFail();
        if ($locked->reversal()->exists()) {
            throw ValidationException::withMessages(['reason' => [__('gh.errors.cash_settlement_already_reversed')]]);
        }

        $now = Carbon::now('UTC');
        $reversal = new GhDriverCashSettlementReversal;
        $reversal->forceFill([
            'reference' => $this->numbers->nextReversalReference(),
            'settlement_id' => $locked->id,
            'driver_id' => $locked->driver_id,
            'amount' => GhMoney::of((string) $locked->amount),
            'currency' => 'AED',
            'reason' => $reason,
            'reversed_by' => $manager->id,
            'reversed_at' => $now,
            'idempotency_key' => $key,
        ])->save();

        $after = $this->ledger->applyBalanceDelta($locked->driver_id, '0.00', GhMoney::of(GhMoney::sub('0', (string) $locked->amount, 2)));
        $this->ledger->recordEvent($locked->driver_id, 'SETTLEMENT_REVERSED', $manager, null, $locked->id, [
            'reference' => $reversal->reference,
            'settlement_reference' => $locked->reference,
            'amount' => $reversal->amount,
            'reason' => $reason,
            'outstanding_after' => GhMoney::of((string) $after->outstanding),
        ], $now);

        return $reversal->fresh();
    }

    private function assertWithinOutstanding(int $driverId, string $received, string $outstanding): void
    {
        if (GhMoney::cmp($received, $outstanding) <= 0) {
            return;
        }
        $unallocated = $this->driverUnallocated($driverId);
        $message = GhMoney::cmp($unallocated, '0') > 0
            ? __('gh.errors.cash_settlement_exceeds_outstanding_legacy', ['outstanding' => $outstanding, 'unallocated' => $unallocated])
            : __('gh.errors.cash_settlement_exceeds_outstanding', ['outstanding' => $outstanding]);

        throw ValidationException::withMessages(['received_amount' => [$message]]);
    }

    private function driverUnallocated(int $driverId): string
    {
        $settled = (string) GhDriverCashSettlement::query()->where('driver_id', $driverId)->whereDoesntHave('reversal')->sum('amount');
        $allocated = (string) GhDriverCashSettlementAllocation::query()->effective()->where('driver_id', $driverId)->sum('amount');

        return GhMoney::of(GhMoney::sub($settled, $allocated, 2));
    }

    private function authorizeActor(User $driver, User $manager): void
    {
        if (! GhCatalogAccess::mayManage($manager)) {
            throw ValidationException::withMessages(['settlement' => [__('gh.errors.cash_settlement_forbidden')]]);
        }
        if ((int) $manager->id === (int) $driver->id) {
            throw ValidationException::withMessages(['settlement' => [__('gh.errors.cash_settlement_self_forbidden')]]);
        }
    }

    private function requireKey(mixed $raw): string
    {
        $key = trim((string) $raw);
        if ($key === '' || strlen($key) > 64) {
            throw ValidationException::withMessages(['idempotency_key' => [__('gh.errors.cash_settlement_idempotency_required')]]);
        }

        return $key;
    }
}
