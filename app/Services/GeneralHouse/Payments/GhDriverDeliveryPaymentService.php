<?php

namespace App\Services\GeneralHouse\Payments;

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
 * One-step driver delivery payment: CASH or ONLINE → CONFIRMED immediately.
 * Settlement / cash reconciliation is out of scope.
 */
class GhDriverDeliveryPaymentService
{
    public function __construct(
        private readonly GhPaymentService $payments,
        private readonly GhPaymentBalanceService $balances,
    ) {}

    /**
     * @param  array{
     *   payment_method?: string|null,
     *   idempotency_key?: string|null,
     *   notes?: string|null,
     *   occurred_at?: string|null,
     * }  $data
     * @return array{payment: ?GhPayment, notes: ?string}
     */
    public function handleBeforeDeliveryConfirm(GhOrder $order, User $driver, array $data): array
    {
        $order->loadMissing('invoice');
        $invoice = $order->invoice;
        $notes = $data['notes'] ?? null;

        if ($invoice === null || ! $invoice->isIssued()) {
            return ['payment' => null, 'notes' => $notes];
        }

        if (! GhLaunchConfig::driverDeliveryPaymentEnabled()) {
            return ['payment' => null, 'notes' => $notes];
        }

        $balance = $this->balances->forInvoice($invoice);
        if (GhMoney::cmp($balance['remaining_due'], '0') <= 0) {
            return ['payment' => null, 'notes' => $notes];
        }

        if (! GhOrderAccess::mayConfirmDelivery($driver, $order)) {
            throw ValidationException::withMessages([
                'order' => [__('gh.errors.delivery_confirm_forbidden')],
            ]);
        }

        $method = strtoupper(trim((string) ($data['payment_method'] ?? '')));
        if (! in_array($method, [GhPaymentMethod::CASH, GhPaymentMethod::ONLINE], true)) {
            throw ValidationException::withMessages([
                'payment_method' => [__('gh.errors.driver_payment_method_required')],
            ]);
        }

        $idempotency = trim((string) ($data['idempotency_key'] ?? ''));
        $payment = $this->payments->recordDriverDeliveryPayment($invoice, $driver, [
            'method' => $method,
            'amount' => $balance['remaining_due'],
            'idempotency_key' => $idempotency !== '' ? $idempotency : $this->stableKey($invoice),
            'notes' => $notes,
            'occurred_at' => $data['occurred_at'] ?? null,
        ]);

        return ['payment' => $payment, 'notes' => $notes];
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
