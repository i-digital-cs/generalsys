<?php

namespace App\Services\GeneralHouse\Corrections;

use App\Models\GeneralHouse\GhFinancialCorrection;
use App\Models\GeneralHouse\GhFinancialCorrectionEvent;
use App\Models\GeneralHouse\GhInvoice;
use App\Models\GeneralHouse\GhPayment;
use App\Models\User;
use App\Services\GeneralHouse\Payments\GhPaymentBalanceService;
use App\Support\GeneralHouse\GhFinancialCorrectionStatus;
use App\Support\GeneralHouse\GhFinancialCorrectionType;
use App\Support\GeneralHouse\GhLaunchConfig;
use App\Support\GeneralHouse\GhMoney;
use App\Support\GeneralHouse\GhPaymentStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Payment recording error workflow.
 * EXECUTE is blocked until owner enables payment_correction_execute_enabled
 * (accounting policy for ledger reversals unresolved).
 */
class GhFinancialCorrectionService
{
    public function __construct(
        private readonly GhFinancialCorrectionNumberService $numbers,
        private readonly GhPaymentBalanceService $balances,
    ) {}

    public function requestPaymentRecordingError(GhPayment $payment, User $actor, array $input): GhFinancialCorrection
    {
        if ($payment->status !== GhPaymentStatus::CONFIRMED) {
            throw ValidationException::withMessages(['payment' => 'Only CONFIRMED payments can be flagged for recording correction.']);
        }

        $idempotency = (string) ($input['idempotency_key'] ?? Str::uuid());
        $existing = GhFinancialCorrection::query()->where('idempotency_key', $idempotency)->first();
        if ($existing !== null) {
            return $existing;
        }

        $reason = trim((string) ($input['reason_text'] ?? ''));
        if ($reason === '') {
            throw ValidationException::withMessages(['reason_text' => 'Reason text is required.']);
        }

        $amount = GhMoney::of((string) $payment->amount);

        return DB::transaction(function () use ($payment, $actor, $idempotency, $reason, $amount) {
            $open = GhFinancialCorrection::query()
                ->where('payment_id', $payment->id)
                ->where('correction_type', GhFinancialCorrectionType::PAYMENT_RECORDING_ERROR)
                ->whereNotIn('status', [
                    GhFinancialCorrectionStatus::REJECTED,
                    GhFinancialCorrectionStatus::CANCELLED,
                    GhFinancialCorrectionStatus::EXECUTED,
                ])
                ->exists();
            if ($open) {
                throw ValidationException::withMessages([
                    'payment' => 'An open payment recording correction already exists for this payment.',
                ]);
            }

            $corr = new GhFinancialCorrection;
            $corr->forceFill([
                'reference' => null,
                'correction_type' => GhFinancialCorrectionType::PAYMENT_RECORDING_ERROR,
                'status' => GhFinancialCorrectionStatus::REQUESTED,
                'invoice_id' => $payment->invoice_id,
                'payment_id' => $payment->id,
                'amount' => $amount,
                'currency' => $payment->currency ?: 'AED',
                'reason_text' => $reason,
                'idempotency_key' => $idempotency,
                'payload' => [
                    'payment_reference' => $payment->reference,
                    'payment_amount' => (string) $payment->amount,
                    'note' => 'Original CONFIRMED payment is not modified.',
                ],
                'requested_by' => $actor->id,
                'requested_at' => Carbon::now('UTC'),
            ])->save();

            $this->recordEvent($corr, $actor, 'REQUESTED', null, GhFinancialCorrectionStatus::REQUESTED);

            return $corr->fresh();
        });
    }

    public function submitForApproval(GhFinancialCorrection $corr, User $actor): GhFinancialCorrection
    {
        return DB::transaction(function () use ($corr, $actor) {
            $corr = GhFinancialCorrection::query()->whereKey($corr->id)->lockForUpdate()->firstOrFail();
            if ($corr->status !== GhFinancialCorrectionStatus::REQUESTED) {
                throw ValidationException::withMessages(['status' => 'Only requested corrections can be submitted.']);
            }
            $from = $corr->status;
            $corr->forceFill(['status' => GhFinancialCorrectionStatus::PENDING_APPROVAL])->save();
            $this->recordEvent($corr, $actor, 'SUBMITTED', $from, GhFinancialCorrectionStatus::PENDING_APPROVAL);

            return $corr->fresh();
        });
    }

    public function approve(GhFinancialCorrection $corr, User $actor): GhFinancialCorrection
    {
        return DB::transaction(function () use ($corr, $actor) {
            $corr = GhFinancialCorrection::query()->whereKey($corr->id)->lockForUpdate()->firstOrFail();
            if ($corr->status !== GhFinancialCorrectionStatus::PENDING_APPROVAL) {
                throw ValidationException::withMessages(['status' => 'Correction is not pending approval.']);
            }
            if (GhLaunchConfig::requireDistinctApprover()
                && (int) $corr->requested_by === (int) $actor->id) {
                throw ValidationException::withMessages([
                    'approver' => 'Approver must be different from the requester (separation of duties).',
                ]);
            }
            $from = $corr->status;
            $corr->forceFill([
                'status' => GhFinancialCorrectionStatus::APPROVED,
                'approved_by' => $actor->id,
                'approved_at' => Carbon::now('UTC'),
            ])->save();
            $this->recordEvent($corr, $actor, 'APPROVED', $from, GhFinancialCorrectionStatus::APPROVED);

            return $corr->fresh();
        });
    }

    public function reject(GhFinancialCorrection $corr, User $actor, string $reason): GhFinancialCorrection
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['rejection_reason' => 'Rejection reason is required.']);
        }

        return DB::transaction(function () use ($corr, $actor, $reason) {
            $corr = GhFinancialCorrection::query()->whereKey($corr->id)->lockForUpdate()->firstOrFail();
            if ($corr->status !== GhFinancialCorrectionStatus::PENDING_APPROVAL) {
                throw ValidationException::withMessages(['status' => 'Only pending corrections can be rejected.']);
            }
            $from = $corr->status;
            $corr->forceFill([
                'status' => GhFinancialCorrectionStatus::REJECTED,
                'rejected_by' => $actor->id,
                'rejected_at' => Carbon::now('UTC'),
                'rejection_reason' => $reason,
            ])->save();
            $this->recordEvent($corr, $actor, 'REJECTED', $from, GhFinancialCorrectionStatus::REJECTED, ['reason' => $reason]);

            return $corr->fresh();
        });
    }

    public function execute(GhFinancialCorrection $corr, User $actor): GhFinancialCorrection
    {
        if (! GhLaunchConfig::paymentCorrectionExecuteEnabled()) {
            throw ValidationException::withMessages([
                'execute' => 'Payment correction execution is blocked until the owner approves the accounting reversal policy.',
            ]);
        }

        return DB::transaction(function () use ($corr, $actor) {
            $corr = GhFinancialCorrection::query()->whereKey($corr->id)->lockForUpdate()->firstOrFail();
            if ($corr->status === GhFinancialCorrectionStatus::EXECUTED) {
                return $corr->fresh();
            }
            if ($corr->status !== GhFinancialCorrectionStatus::APPROVED) {
                throw ValidationException::withMessages(['status' => 'Only approved corrections can be executed.']);
            }
            if (GhLaunchConfig::requireDistinctPayoutActor()
                && (int) $corr->approved_by === (int) $actor->id) {
                throw ValidationException::withMessages([
                    'execute' => 'Executor must be different from the approver (separation of duties).',
                ]);
            }

            // Original payment row remains CONFIRMED and immutable; EXECUTED correction
            // reduces cash_net via balance SSOT (payment_reversals).
            $invoice = GhInvoice::query()->whereKey($corr->invoice_id)->lockForUpdate()->firstOrFail();
            $from = $corr->status;
            $reference = $corr->reference ?: $this->numbers->nextReference();
            $corr->forceFill([
                'reference' => $reference,
                'status' => GhFinancialCorrectionStatus::EXECUTED,
                'executed_by' => $actor->id,
                'executed_at' => Carbon::now('UTC'),
            ])->save();
            $this->recordEvent($corr, $actor, 'EXECUTED', $from, GhFinancialCorrectionStatus::EXECUTED, [
                'reference' => $reference,
            ]);
            $this->balances->syncInvoicePaymentStatus($invoice);

            return $corr->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function recordEvent(
        GhFinancialCorrection $corr,
        User $actor,
        string $type,
        ?string $from,
        string $to,
        array $payload = [],
    ): void {
        $event = new GhFinancialCorrectionEvent;
        $event->forceFill([
            'financial_correction_id' => $corr->id,
            'invoice_id' => $corr->invoice_id,
            'event_type' => $type,
            'from_status' => $from,
            'to_status' => $to,
            'actor_id' => $actor->id,
            'occurred_at' => Carbon::now('UTC'),
            'payload' => $payload === [] ? null : $payload,
        ])->save();
    }
}
