<?php

namespace App\Services\GeneralHouse\Payments;

use App\Models\GeneralHouse\GhInvoice;
use App\Models\GeneralHouse\GhPayment;
use App\Models\GeneralHouse\GhPaymentEvent;
use App\Models\User;
use App\Support\GeneralHouse\GhInvoiceStatus;
use App\Support\GeneralHouse\GhLaunchConfig;
use App\Support\GeneralHouse\GhMoney;
use App\Support\GeneralHouse\GhPaymentMethod;
use App\Support\GeneralHouse\GhPaymentStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GhPaymentService
{
    public function __construct(
        private readonly GhPaymentBalanceService $balances,
        private readonly GhPaymentNumberService $numbers,
    ) {}

    /**
     * Manager-authorized cash collection: recorded and CONFIRMED in one step.
     * Drivers cannot call this (policy). Idempotent via idempotency_key.
     *
     * @param  array{
     *   amount: string|float|int,
     *   occurred_at?: string|null,
     *   notes?: string|null,
     *   idempotency_key?: string|null,
     *   collector_user_id?: int|null,
     * }  $data
     */
    public function recordConfirmedCash(GhInvoice $invoice, User $actor, array $data): GhPayment
    {
        if (! GhLaunchConfig::cashEnabled()) {
            throw ValidationException::withMessages([
                'method' => [__('gh.errors.payment_cash_disabled')],
            ]);
        }

        return $this->recordPayment($invoice, $actor, [
            'method' => GhPaymentMethod::CASH,
            'status' => GhPaymentStatus::CONFIRMED,
            'amount' => $data['amount'],
            'occurred_at' => $data['occurred_at'] ?? null,
            'notes' => $data['notes'] ?? null,
            'idempotency_key' => $data['idempotency_key'] ?? null,
            'collector_user_id' => $data['collector_user_id'] ?? $actor->id,
            'confirm_immediately' => true,
        ]);
    }

    /**
     * Online payment initiation stub — NEVER marks invoice PAID.
     * Gateway confirmation is disabled until a provider is owner-approved.
     *
     * @param  array{
     *   amount: string|float|int,
     *   external_ref?: string|null,
     *   notes?: string|null,
     *   idempotency_key?: string|null,
     * }  $data
     */
    public function initiateOnline(GhInvoice $invoice, User $actor, array $data): GhPayment
    {
        if (! GhLaunchConfig::onlineInitiationEnabled()) {
            throw ValidationException::withMessages([
                'method' => [__('gh.errors.payment_online_disabled')],
            ]);
        }

        return $this->recordPayment($invoice, $actor, [
            'method' => GhPaymentMethod::ONLINE,
            'status' => GhPaymentStatus::PENDING,
            'amount' => $data['amount'],
            'external_ref' => $data['external_ref'] ?? null,
            'notes' => $data['notes'] ?? null,
            'idempotency_key' => $data['idempotency_key'] ?? null,
            'confirm_immediately' => false,
        ]);
    }

    public function cancelPending(GhPayment $payment, User $actor, ?string $reason = null): GhPayment
    {
        return DB::transaction(function () use ($payment, $actor, $reason) {
            /** @var GhPayment $locked */
            $locked = GhPayment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === GhPaymentStatus::CANCELLED) {
                return $locked->fresh();
            }

            if (! in_array($locked->status, [GhPaymentStatus::PENDING, GhPaymentStatus::PENDING_REVIEW], true)) {
                throw ValidationException::withMessages([
                    'payment' => [__('gh.errors.payment_cancel_not_pending')],
                ]);
            }

            $from = $locked->status;
            $now = Carbon::now('UTC');
            $locked->forceFill([
                'status' => GhPaymentStatus::CANCELLED,
                'cancelled_by' => $actor->id,
                'cancelled_at' => $now,
                'notes' => trim(($locked->notes ? $locked->notes."\n" : '').($reason ?? '')),
            ]);
            $locked->save();

            $this->appendEvent($locked, 'CANCELLED', $from, GhPaymentStatus::CANCELLED, $actor, [
                'reason' => $reason,
            ]);

            return $locked->fresh();
        });
    }

    /**
     * Explicitly refuse gateway confirmation until provider approved.
     */
    public function confirmOnlineGateway(GhPayment $payment, User $actor): never
    {
        if (! GhLaunchConfig::onlineGatewayConfirmEnabled()) {
            throw ValidationException::withMessages([
                'payment' => [__('gh.errors.payment_gateway_not_configured')],
            ]);
        }

        throw ValidationException::withMessages([
            'payment' => [__('gh.errors.payment_gateway_not_configured')],
        ]);
    }

    /**
     * Driver one-step delivery collection — CONFIRMED immediately (idempotent).
     * ONLINE is operator-recorded, not gateway-verified (no fake provider refs).
     *
     * @param  array{
     *   method: string,
     *   amount: string|float|int,
     *   occurred_at?: string|null,
     *   notes?: string|null,
     *   idempotency_key?: string|null,
     * }  $data
     */
    public function recordDriverDeliveryPayment(GhInvoice $invoice, User $driver, array $data): GhPayment
    {
        if (! GhLaunchConfig::driverDeliveryPaymentEnabled()) {
            throw ValidationException::withMessages([
                'method' => [__('gh.errors.driver_payment_declaration_disabled')],
            ]);
        }

        $method = strtoupper(trim((string) ($data['method'] ?? '')));
        if (! in_array($method, [GhPaymentMethod::CASH, GhPaymentMethod::ONLINE], true)) {
            throw ValidationException::withMessages([
                'method' => [__('gh.errors.driver_payment_method_required')],
            ]);
        }

        if ($method === GhPaymentMethod::CASH && ! GhLaunchConfig::cashEnabled()) {
            throw ValidationException::withMessages([
                'method' => [__('gh.errors.payment_cash_disabled')],
            ]);
        }

        $notes = trim((string) ($data['notes'] ?? ''));
        $eventPayload = [
            'source' => 'driver_delivery',
            'collector_user_id' => $driver->id,
        ];

        if ($method === GhPaymentMethod::ONLINE) {
            $eventPayload['verification_source'] = 'OPERATOR_RECORDED';
            $eventPayload['gateway_verified'] = false;
            $marker = __('gh.payments.online_operator_recorded_note');
            $notes = trim(($notes !== '' ? $notes."\n" : '').$marker);
        } else {
            $eventPayload['verification_source'] = 'DRIVER_CASH';
            $eventPayload['gateway_verified'] = false;
        }

        // One driver-delivery payment per invoice — retries reuse this key.
        return $this->recordPayment($invoice, $driver, [
            'method' => $method,
            'amount' => $data['amount'],
            'occurred_at' => $data['occurred_at'] ?? null,
            'notes' => $notes !== '' ? $notes : null,
            'idempotency_key' => 'gh-driver-delivery-pay-'.$invoice->id,
            'collector_user_id' => $driver->id,
            'confirm_immediately' => true,
            'event_payload' => $eventPayload,
        ]);
    }

    /**
     * Legacy cleanup: manager may still confirm historical PENDING_REVIEW cash rows.
     */
    public function confirmCashDeclaration(GhPayment $payment, User $manager): GhPayment
    {
        if (! GhLaunchConfig::cashEnabled()) {
            throw ValidationException::withMessages([
                'method' => [__('gh.errors.payment_cash_disabled')],
            ]);
        }

        return DB::transaction(function () use ($payment, $manager) {
            /** @var GhPayment $locked */
            $locked = GhPayment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === GhPaymentStatus::CONFIRMED) {
                return $locked->fresh(['events']);
            }

            if ($locked->method !== GhPaymentMethod::CASH
                || $locked->status !== GhPaymentStatus::PENDING_REVIEW) {
                throw ValidationException::withMessages([
                    'payment' => [__('gh.errors.payment_declaration_not_confirmable')],
                ]);
            }

            /** @var GhInvoice $lockedInvoice */
            $lockedInvoice = GhInvoice::query()->whereKey($locked->invoice_id)->lockForUpdate()->firstOrFail();
            $balance = $this->balances->forInvoice($lockedInvoice);
            $amount = GhMoney::of((string) $locked->amount);

            if (GhMoney::cmp($amount, $balance['remaining_due']) > 0
                && ! GhLaunchConfig::overpaymentAllowed()) {
                throw ValidationException::withMessages([
                    'amount' => [__('gh.errors.payment_exceeds_remaining', [
                        'remaining' => $balance['remaining_due'],
                    ])],
                ]);
            }

            $from = $locked->status;
            $now = Carbon::now('UTC');
            $locked->forceFill([
                'status' => GhPaymentStatus::CONFIRMED,
                'confirmed_by' => $manager->id,
                'confirmed_at' => $now,
            ]);
            $locked->save();

            $this->appendEvent($locked, 'CONFIRMED', $from, GhPaymentStatus::CONFIRMED, $manager, [
                'amount' => $amount,
                'source' => 'legacy_driver_declaration',
            ]);

            $after = $this->balances->forInvoice($lockedInvoice->fresh());
            if (GhMoney::cmp($after['cash_net'], $after['net_invoiced']) > 0
                && ! GhLaunchConfig::overpaymentAllowed()) {
                throw ValidationException::withMessages([
                    'amount' => [__('gh.errors.payment_concurrent_overpay')],
                ]);
            }

            $this->balances->syncInvoicePaymentStatus($lockedInvoice);

            return $locked->fresh(['events']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function recordPayment(GhInvoice $invoice, User $actor, array $data): GhPayment
    {
        $method = (string) $data['method'];
        if ($method === GhPaymentMethod::BANK || ! in_array($method, GhPaymentMethod::APPROVED, true)) {
            throw ValidationException::withMessages([
                'method' => [__('gh.errors.payment_method_not_approved')],
            ]);
        }

        if (! GhLaunchConfig::bankTransferEnabled() && $method === GhPaymentMethod::BANK) {
            throw ValidationException::withMessages([
                'method' => [__('gh.errors.payment_bank_disabled')],
            ]);
        }

        $idempotencyKey = trim((string) ($data['idempotency_key'] ?? ''));
        if ($idempotencyKey === '') {
            $idempotencyKey = (string) Str::uuid();
        }

        return DB::transaction(function () use ($invoice, $actor, $data, $method, $idempotencyKey) {
            $existing = GhPayment::query()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existing !== null) {
                return $existing->fresh(['events']);
            }

            /** @var GhInvoice $lockedInvoice */
            $lockedInvoice = GhInvoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($lockedInvoice->status !== GhInvoiceStatus::ISSUED) {
                throw ValidationException::withMessages([
                    'invoice' => [__('gh.errors.payment_requires_issued')],
                ]);
            }

            $amount = GhMoney::of($data['amount'] ?? '0');
            if (GhMoney::cmp($amount, '0') <= 0) {
                throw ValidationException::withMessages([
                    'amount' => [__('gh.errors.payment_amount_invalid')],
                ]);
            }

            $balance = $this->balances->forInvoice($lockedInvoice);
            $confirmNow = (bool) ($data['confirm_immediately'] ?? false);
            $explicitStatus = isset($data['status']) ? (string) $data['status'] : null;

            if ($confirmNow) {
                if (! GhLaunchConfig::partialPaymentsEnabled()
                    && GhMoney::cmp($balance['confirmed_total'], '0') > 0) {
                    throw ValidationException::withMessages([
                        'amount' => [__('gh.errors.payment_partial_disabled')],
                    ]);
                }

                // Cap against remaining_due (net invoiced − cash net), not gross invoice alone.
                if (GhMoney::cmp($amount, $balance['remaining_due']) > 0
                    && ! GhLaunchConfig::overpaymentAllowed()) {
                    throw ValidationException::withMessages([
                        'amount' => [__('gh.errors.payment_exceeds_remaining', [
                            'remaining' => $balance['remaining_due'],
                        ])],
                    ]);
                }
            } elseif (GhMoney::cmp($amount, $balance['remaining_due']) > 0
                && ! GhLaunchConfig::overpaymentAllowed()) {
                // Declarations / pending online also must not exceed remaining.
                throw ValidationException::withMessages([
                    'amount' => [__('gh.errors.payment_exceeds_remaining', [
                        'remaining' => $balance['remaining_due'],
                    ])],
                ]);
            }

            $externalRef = isset($data['external_ref']) ? trim((string) $data['external_ref']) : null;
            if ($externalRef === '') {
                $externalRef = null;
            }

            if ($externalRef !== null) {
                $dupExt = GhPayment::query()
                    ->where('invoice_id', $lockedInvoice->id)
                    ->where('external_ref', $externalRef)
                    ->exists();
                if ($dupExt) {
                    throw ValidationException::withMessages([
                        'external_ref' => [__('gh.errors.payment_external_ref_duplicate')],
                    ]);
                }
            }

            $occurredAt = $this->parseOccurredAt($data['occurred_at'] ?? null);
            $recordedAt = Carbon::now('UTC');
            if ($confirmNow) {
                $status = GhPaymentStatus::CONFIRMED;
            } elseif ($explicitStatus !== null && in_array($explicitStatus, GhPaymentStatus::ALL, true)) {
                $status = $explicitStatus;
            } else {
                $status = GhPaymentStatus::PENDING;
            }

            $payment = GhPayment::query()->forceCreate([
                'invoice_id' => $lockedInvoice->id,
                'reference' => $this->numbers->nextReference(),
                'method' => $method,
                'status' => $status,
                'amount' => $amount,
                'currency' => 'AED',
                'external_ref' => $externalRef,
                'idempotency_key' => $idempotencyKey,
                'notes' => $data['notes'] ?? null,
                'collector_user_id' => $data['collector_user_id'] ?? ($method === GhPaymentMethod::CASH ? $actor->id : null),
                'recorded_by' => $actor->id,
                'confirmed_by' => $confirmNow ? $actor->id : null,
                'occurred_at' => $occurredAt,
                'recorded_at' => $recordedAt,
                'confirmed_at' => $confirmNow ? $recordedAt : null,
            ]);

            $eventType = match ($status) {
                GhPaymentStatus::CONFIRMED => 'CONFIRMED',
                GhPaymentStatus::PENDING_REVIEW => 'DECLARED',
                default => 'INITIATED',
            };
            $this->appendEvent($payment, $eventType, null, $status, $actor, array_merge([
                'method' => $method,
                'amount' => $amount,
            ], is_array($data['event_payload'] ?? null) ? $data['event_payload'] : []));

            if ($confirmNow) {
                // Re-lock and re-check balance against concurrent confirmed collections.
                $lockedInvoice = GhInvoice::query()->whereKey($lockedInvoice->id)->lockForUpdate()->firstOrFail();
                $after = $this->balances->forInvoice($lockedInvoice);
                if (GhMoney::cmp($after['cash_net'], $after['net_invoiced']) > 0
                    && ! GhLaunchConfig::overpaymentAllowed()) {
                    throw ValidationException::withMessages([
                        'amount' => [__('gh.errors.payment_concurrent_overpay')],
                    ]);
                }
                $this->balances->syncInvoicePaymentStatus($lockedInvoice);
            }

            // Never mutate issued invoice line snapshots / totals.
            return $payment->fresh(['events']);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function appendEvent(
        GhPayment $payment,
        string $eventType,
        ?string $from,
        ?string $to,
        User $actor,
        array $payload = [],
    ): void {
        GhPaymentEvent::query()->create([
            'payment_id' => $payment->id,
            'invoice_id' => $payment->invoice_id,
            'event_type' => $eventType,
            'from_status' => $from,
            'to_status' => $to,
            'actor_id' => $actor->id,
            'occurred_at' => Carbon::now('UTC'),
            'payload' => $payload,
        ]);
    }

    private function parseOccurredAt(mixed $value): Carbon
    {
        if ($value instanceof Carbon) {
            return $value->copy()->utc();
        }
        if (is_string($value) && trim($value) !== '') {
            return Carbon::parse($value, 'UTC');
        }

        return Carbon::now('UTC');
    }
}
