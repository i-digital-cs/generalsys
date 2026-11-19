<?php

namespace App\Services\GeneralHouse\Payments;

use App\Models\GeneralHouse\GhDriverCashBalance;
use App\Models\GeneralHouse\GhDriverCashCollection;
use App\Models\GeneralHouse\GhOrder;
use App\Models\GeneralHouse\GhPayment;
use App\Models\User;
use App\Support\GeneralHouse\GhMoney;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Physical driver cash custody — collection side.
 * Collections increase outstanding custody; manager settlements (GhDriverCashSettlementService) decrease it.
 * Custody is NOT a customer payment and never affects invoice AR or revenue KPIs.
 */
final class GhDriverCashCustodyService
{
    public function __construct(
        private readonly GhDriverCashNumberService $numbers,
        private readonly GhDriverCashLedgerService $ledger,
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

            $this->ledger->applyBalanceDelta($driver->id, $amount, '0.00');
            $this->ledger->recordEvent(
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

    public function outstandingFor(User $driver): string
    {
        $row = GhDriverCashBalance::query()->find($driver->id);

        return GhMoney::of((string) ($row?->outstanding ?? '0'));
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
