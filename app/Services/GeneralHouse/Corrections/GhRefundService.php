<?php

namespace App\Services\GeneralHouse\Corrections;

use App\Models\GeneralHouse\GhCreditNote;
use App\Models\GeneralHouse\GhInvoice;
use App\Models\GeneralHouse\GhRefund;
use App\Models\GeneralHouse\GhRefundEvent;
use App\Models\User;
use App\Services\GeneralHouse\Payments\GhPaymentBalanceService;
use App\Support\GeneralHouse\GhCreditNoteStatus;
use App\Support\GeneralHouse\GhInvoiceStatus;
use App\Support\GeneralHouse\GhLaunchConfig;
use App\Support\GeneralHouse\GhMoney;
use App\Support\GeneralHouse\GhRefundStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GhRefundService
{
    public function __construct(
        private readonly GhRefundNumberService $numbers,
        private readonly GhPaymentBalanceService $balances,
    ) {}

    public function request(GhInvoice $invoice, User $actor, array $input): GhRefund
    {
        $this->assertRefundsEnabled();
        if ($invoice->status !== GhInvoiceStatus::ISSUED) {
            throw ValidationException::withMessages(['invoice' => 'Refunds require an issued invoice.']);
        }

        $idempotency = (string) ($input['idempotency_key'] ?? Str::uuid());
        $existing = GhRefund::query()->where('idempotency_key', $idempotency)->first();
        if ($existing !== null) {
            return $existing;
        }

        $amount = GhMoney::of((string) ($input['amount'] ?? '0'));
        if (GhMoney::cmp($amount, '0') <= 0) {
            throw ValidationException::withMessages(['amount' => 'Refund amount must be greater than zero.']);
        }

        $reasonCode = (string) ($input['reason_code'] ?? 'CUSTOMER_REFUND');
        $reasonText = trim((string) ($input['reason_text'] ?? ''));
        if ($reasonText === '') {
            throw ValidationException::withMessages(['reason_text' => 'Reason text is required.']);
        }

        $creditNoteId = isset($input['credit_note_id']) ? (int) $input['credit_note_id'] : null;
        if ($creditNoteId) {
            $cn = GhCreditNote::query()->find($creditNoteId);
            if ($cn === null || (int) $cn->invoice_id !== (int) $invoice->id || $cn->status !== GhCreditNoteStatus::ISSUED) {
                throw ValidationException::withMessages(['credit_note_id' => 'Credit note must be issued for this invoice.']);
            }
        }

        return DB::transaction(function () use ($invoice, $actor, $idempotency, $amount, $reasonCode, $reasonText, $creditNoteId, $input) {
            GhInvoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            $available = $this->balances->availableRefundable($invoice);
            if (GhMoney::cmp($amount, $available) > 0) {
                throw ValidationException::withMessages([
                    'amount' => "Amount exceeds refundable balance ({$available}).",
                ]);
            }

            $refund = new GhRefund;
            $refund->forceFill([
                'invoice_id' => $invoice->id,
                'customer_id' => $invoice->customer_id,
                'credit_note_id' => $creditNoteId,
                'reference' => null,
                'status' => GhRefundStatus::REQUESTED,
                'method' => 'CASH',
                'amount' => $amount,
                'currency' => $invoice->currency ?: 'AED',
                'reason_code' => $reasonCode,
                'reason_text' => $reasonText,
                'evidence_notes' => $input['evidence_notes'] ?? null,
                'idempotency_key' => $idempotency,
                'requested_by' => $actor->id,
                'requested_at' => Carbon::now('UTC'),
            ])->save();

            $this->recordEvent($refund, $actor, 'REQUESTED', null, GhRefundStatus::REQUESTED, [
                'amount' => $amount,
            ]);

            return $refund->fresh();
        });
    }

    public function submitForApproval(GhRefund $refund, User $actor): GhRefund
    {
        $this->assertRefundsEnabled();

        return DB::transaction(function () use ($refund, $actor) {
            $refund = GhRefund::query()->whereKey($refund->id)->lockForUpdate()->firstOrFail();
            if ($refund->status !== GhRefundStatus::REQUESTED) {
                throw ValidationException::withMessages(['status' => 'Only requested refunds can be submitted.']);
            }
            $this->assertStillRefundable($refund);

            $from = $refund->status;
            $refund->forceFill([
                'status' => GhRefundStatus::PENDING_APPROVAL,
            ])->save();
            $this->recordEvent($refund, $actor, 'SUBMITTED', $from, GhRefundStatus::PENDING_APPROVAL);

            return $refund->fresh();
        });
    }

    public function approve(GhRefund $refund, User $actor): GhRefund
    {
        $this->assertRefundsEnabled();

        return DB::transaction(function () use ($refund, $actor) {
            $refund = GhRefund::query()->whereKey($refund->id)->lockForUpdate()->firstOrFail();
            if ($refund->status !== GhRefundStatus::PENDING_APPROVAL) {
                throw ValidationException::withMessages(['status' => 'Refund is not pending approval.']);
            }
            if (GhLaunchConfig::requireDistinctApprover()
                && (int) $refund->requested_by === (int) $actor->id) {
                throw ValidationException::withMessages([
                    'approver' => 'Approver must be different from the requester (separation of duties).',
                ]);
            }
            $this->assertStillRefundable($refund);

            $from = $refund->status;
            $refund->forceFill([
                'status' => GhRefundStatus::APPROVED,
                'approved_by' => $actor->id,
                'approved_at' => Carbon::now('UTC'),
            ])->save();
            $this->recordEvent($refund, $actor, 'APPROVED', $from, GhRefundStatus::APPROVED);

            return $refund->fresh();
        });
    }

    public function reject(GhRefund $refund, User $actor, string $reason): GhRefund
    {
        $this->assertRefundsEnabled();
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['rejection_reason' => 'Rejection reason is required.']);
        }

        return DB::transaction(function () use ($refund, $actor, $reason) {
            $refund = GhRefund::query()->whereKey($refund->id)->lockForUpdate()->firstOrFail();
            if ($refund->status !== GhRefundStatus::PENDING_APPROVAL) {
                throw ValidationException::withMessages(['status' => 'Only pending refunds can be rejected.']);
            }
            $from = $refund->status;
            $refund->forceFill([
                'status' => GhRefundStatus::REJECTED,
                'rejected_by' => $actor->id,
                'rejected_at' => Carbon::now('UTC'),
                'rejection_reason' => $reason,
            ])->save();
            $this->recordEvent($refund, $actor, 'REJECTED', $from, GhRefundStatus::REJECTED, ['reason' => $reason]);

            return $refund->fresh();
        });
    }

    public function payOut(GhRefund $refund, User $actor): GhRefund
    {
        $this->assertRefundsEnabled();

        return DB::transaction(function () use ($refund, $actor) {
            $refund = GhRefund::query()->whereKey($refund->id)->lockForUpdate()->firstOrFail();
            if ($refund->status === GhRefundStatus::PAID_OUT) {
                return $refund->fresh();
            }
            if ($refund->status !== GhRefundStatus::APPROVED) {
                throw ValidationException::withMessages(['status' => 'Only approved refunds can be paid out.']);
            }
            if (GhLaunchConfig::requireDistinctPayoutActor()
                && (int) $refund->approved_by === (int) $actor->id) {
                throw ValidationException::withMessages([
                    'payout' => 'Payout actor must be different from the approver (separation of duties).',
                ]);
            }

            $invoice = GhInvoice::query()->whereKey($refund->invoice_id)->lockForUpdate()->firstOrFail();
            $available = $this->balances->availableRefundable($invoice, $refund->id);
            if (GhMoney::cmp((string) $refund->amount, $available) > 0) {
                throw ValidationException::withMessages([
                    'amount' => "Refund exceeds available customer credit ({$available}).",
                ]);
            }

            $from = $refund->status;
            $reference = $refund->reference ?: $this->numbers->nextReference();
            $refund->forceFill([
                'reference' => $reference,
                'status' => GhRefundStatus::PAID_OUT,
                'paid_out_by' => $actor->id,
                'paid_out_at' => Carbon::now('UTC'),
            ])->save();
            $this->recordEvent($refund, $actor, 'PAID_OUT', $from, GhRefundStatus::PAID_OUT, [
                'reference' => $reference,
                'amount' => (string) $refund->amount,
            ]);
            $this->balances->syncInvoicePaymentStatus($invoice);

            return $refund->fresh();
        });
    }

    public function cancel(GhRefund $refund, User $actor): GhRefund
    {
        $this->assertRefundsEnabled();

        return DB::transaction(function () use ($refund, $actor) {
            $refund = GhRefund::query()->whereKey($refund->id)->lockForUpdate()->firstOrFail();
            if (! in_array($refund->status, [
                GhRefundStatus::REQUESTED,
                GhRefundStatus::PENDING_APPROVAL,
                GhRefundStatus::APPROVED,
            ], true)) {
                throw ValidationException::withMessages(['status' => 'Paid-out refunds cannot be cancelled.']);
            }
            $from = $refund->status;
            $refund->forceFill([
                'status' => GhRefundStatus::CANCELLED,
                'cancelled_by' => $actor->id,
                'cancelled_at' => Carbon::now('UTC'),
            ])->save();
            $this->recordEvent($refund, $actor, 'CANCELLED', $from, GhRefundStatus::CANCELLED);

            return $refund->fresh();
        });
    }

    private function assertStillRefundable(GhRefund $refund): void
    {
        $invoice = GhInvoice::query()->findOrFail($refund->invoice_id);
        $available = $this->balances->availableRefundable($invoice, $refund->id);
        if (GhMoney::cmp((string) $refund->amount, $available) > 0) {
            throw ValidationException::withMessages([
                'amount' => "Amount exceeds refundable balance ({$available}).",
            ]);
        }
    }

    private function assertRefundsEnabled(): void
    {
        if (! GhLaunchConfig::refundsEnabled()) {
            throw ValidationException::withMessages(['refunds' => 'Cash refunds are disabled.']);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function recordEvent(
        GhRefund $refund,
        User $actor,
        string $type,
        ?string $from,
        string $to,
        array $payload = [],
    ): void {
        $event = new GhRefundEvent;
        $event->forceFill([
            'refund_id' => $refund->id,
            'invoice_id' => $refund->invoice_id,
            'event_type' => $type,
            'from_status' => $from,
            'to_status' => $to,
            'actor_id' => $actor->id,
            'occurred_at' => Carbon::now('UTC'),
            'payload' => $payload === [] ? null : $payload,
        ])->save();
    }
}
