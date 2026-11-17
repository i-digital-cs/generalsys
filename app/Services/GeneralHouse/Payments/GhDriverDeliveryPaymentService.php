<?php

namespace App\Services\GeneralHouse\Payments;

use App\Models\GeneralHouse\GhDriverCashCollection;
use App\Models\GeneralHouse\GhInvoice;
use App\Models\GeneralHouse\GhOrder;
use App\Models\GeneralHouse\GhPayment;
use App\Models\User;
use App\Support\GeneralHouse\GhLaunchConfig;
use App\Support\GeneralHouse\GhMoney;
use App\Support\GeneralHouse\GhOrderAccess;
use App\Support\GeneralHouse\GhPaymentMethod;
use Illuminate\Validation\ValidationException;

/**
 * Driver delivery payment + optional cash custody.
 * CASH records the actual collected amount (partial allowed).
 * ONLINE keeps operator-recorded invoice payment without custody.
 * Settlement of physical cash is handled by GhDriverCashCustodyService.
 */
class GhDriverDeliveryPaymentService
{
    public function __construct(
        private readonly GhPaymentService $payments,
        private readonly GhPaymentBalanceService $balances,
        private readonly GhDriverCashCustodyService $custody,
    ) {}

    /**
     * @param  array{
     *   payment_method?: string|null,
     *   cash_collected_amount?: string|float|int|null,
     *   idempotency_key?: string|null,
     *   notes?: string|null,
     *   occurred_at?: string|null,
     * }  $data
     * @return array{payment: ?GhPayment, collection: ?GhDriverCashCollection, notes: ?string}
     */
    public function handleBeforeDeliveryConfirm(GhOrder $order, User $driver, array $data): array
    {
        $order->loadMissing('invoice');
        $invoice = $order->invoice;
        $notes = $data['notes'] ?? null;

        // Recomplete after COMPLETED→DELIVERY: original delivery evidence means no new collection.
        if ($order->delivered_at !== null) {
            return ['payment' => null, 'collection' => null, 'notes' => $notes];
        }

        if (! GhLaunchConfig::driverDeliveryPaymentEnabled()) {
            return ['payment' => null, 'collection' => null, 'notes' => $notes];
        }

        if (! GhOrderAccess::mayConfirmDelivery($driver, $order)) {
            throw ValidationException::withMessages([
                'order' => [__('gh.errors.delivery_confirm_forbidden')],
            ]);
        }

        $method = strtoupper(trim((string) ($data['payment_method'] ?? '')));
        $hasIssuedUnpaid = false;
        $remaining = '0.00';
        if ($invoice !== null && $invoice->isIssued()) {
            $balance = $this->balances->forInvoice($invoice);
            $remaining = $balance['remaining_due'];
            $hasIssuedUnpaid = GhMoney::cmp($remaining, '0') > 0;
        }

        // Already paid / no method chosen and nothing due → skip.
        if ($method === '' && ! $hasIssuedUnpaid) {
            return ['payment' => null, 'collection' => null, 'notes' => $notes];
        }

        if ($hasIssuedUnpaid && ! in_array($method, [GhPaymentMethod::CASH, GhPaymentMethod::ONLINE], true)) {
            throw ValidationException::withMessages([
                'payment_method' => [__('gh.errors.driver_payment_method_required')],
            ]);
        }

        if ($method === '' || $method === GhPaymentMethod::ONLINE) {
            if ($method === GhPaymentMethod::ONLINE && $hasIssuedUnpaid) {
                $payment = $this->payments->recordDriverDeliveryPayment($invoice, $driver, [
                    'method' => GhPaymentMethod::ONLINE,
                    'amount' => $remaining,
                    'idempotency_key' => $this->stableKey($invoice),
                    'notes' => $notes,
                    'occurred_at' => $data['occurred_at'] ?? null,
                ]);

                return ['payment' => $payment, 'collection' => null, 'notes' => $notes];
            }

            return ['payment' => null, 'collection' => null, 'notes' => $notes];
        }

        // CASH — require actual collected amount > 0.
        $cashAmount = GhMoney::of($data['cash_collected_amount'] ?? '0');
        if (GhMoney::cmp($cashAmount, '0') <= 0) {
            throw ValidationException::withMessages([
                'cash_collected_amount' => [__('gh.errors.cash_collected_amount_required')],
            ]);
        }

        $payment = null;
        if ($hasIssuedUnpaid) {
            // Apply only up to remaining due — never invent overpayment / full-pay fiction.
            if (GhMoney::cmp($cashAmount, $remaining) > 0) {
                throw ValidationException::withMessages([
                    'cash_collected_amount' => [__('gh.errors.cash_collected_exceeds_remaining', [
                        'remaining' => $remaining,
                    ])],
                ]);
            }

            $payment = $this->payments->recordDriverDeliveryPayment($invoice, $driver, [
                'method' => GhPaymentMethod::CASH,
                'amount' => $cashAmount,
                'idempotency_key' => $this->stableKey($invoice),
                'notes' => $notes,
                'occurred_at' => $data['occurred_at'] ?? null,
            ]);
        }

        $collection = $this->custody->recordDeliveryCashCollection($order, $driver, [
            'amount' => $cashAmount,
            'payment_id' => $payment?->id,
            'notes' => $notes,
            'occurred_at' => $data['occurred_at'] ?? null,
            'idempotency_key' => $this->custody->collectionKeyForOrder($order),
        ]);

        if ($payment !== null && $collection->payment_id === null) {
            $this->custody->attachPaymentIfMissing($collection, $payment);
        }

        return ['payment' => $payment, 'collection' => $collection, 'notes' => $notes];
    }

    /**
     * @return array{
     *   remaining: string,
     *   payment_status: string,
     *   invoice_total: string,
     *   needs_method: bool,
     * }
     */
    public function deliveryPaymentContext(GhInvoice $invoice): array
    {
        $balance = $this->balances->forInvoice($invoice);

        return [
            'remaining' => $balance['remaining_due'],
            'payment_status' => $balance['payment_status'],
            'invoice_total' => $balance['invoice_total'],
            'needs_method' => GhMoney::cmp($balance['remaining_due'], '0') > 0,
        ];
    }

    public function stableKey(GhInvoice $invoice): string
    {
        return 'gh-driver-delivery-pay-'.$invoice->id;
    }
}
