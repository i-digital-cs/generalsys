<?php

namespace App\Services\GeneralHouse\Payments;

use App\Models\GeneralHouse\GhCreditNote;
use App\Models\GeneralHouse\GhFinancialCorrection;
use App\Models\GeneralHouse\GhInvoice;
use App\Models\GeneralHouse\GhPayment;
use App\Models\GeneralHouse\GhRefund;
use App\Support\GeneralHouse\GhCreditNoteStatus;
use App\Support\GeneralHouse\GhFinancialCorrectionStatus;
use App\Support\GeneralHouse\GhFinancialCorrectionType;
use App\Support\GeneralHouse\GhInvoicePaymentStatus;
use App\Support\GeneralHouse\GhMoney;
use App\Support\GeneralHouse\GhPaymentStatus;
use App\Support\GeneralHouse\GhRefundStatus;

/**
 * Single source of truth for GH invoice payment / correction balances.
 * Controllers / Blade / JS are consumers only.
 *
 * @see docs/general-house/GH10_BALANCE_INVARIANTS.md
 */
class GhPaymentBalanceService
{
    /**
     * @return array{
     *   invoice_total: string,
     *   credits_issued: string,
     *   net_invoiced: string,
     *   confirmed_total: string,
     *   pending_total: string,
     *   refunds_paid_out: string,
     *   payment_reversals: string,
     *   cash_net: string,
     *   remaining: string,
     *   remaining_due: string,
     *   customer_credit: string,
     *   refundable: string,
     *   refunds_reserving: string,
     *   payment_status: string,
     *   is_paid: bool,
     *   currency: string,
     * }
     */
    public function forInvoice(GhInvoice $invoice): array
    {
        $gross = GhMoney::of((string) $invoice->total_payable);
        $credits = $this->sumIssuedCredits($invoice->id);
        $net = GhMoney::of(GhMoney::sub($gross, $credits));
        if (GhMoney::cmp($net, '0') < 0) {
            $net = '0.00';
        }

        $confirmed = $this->sumConfirmed($invoice->id);
        $pending = $this->sumPending($invoice->id);
        $refundsPaid = $this->sumPaidOutRefunds($invoice->id);
        $reversals = $this->sumExecutedPaymentReversals($invoice->id);
        $cashNet = GhMoney::of(GhMoney::sub(GhMoney::sub($confirmed, $refundsPaid), $reversals));
        if (GhMoney::cmp($cashNet, '0') < 0) {
            $cashNet = '0.00';
        }

        $remainingDue = GhMoney::of(GhMoney::sub($net, $cashNet));
        if (GhMoney::cmp($remainingDue, '0') < 0) {
            $remainingDue = '0.00';
        }

        $customerCredit = GhMoney::of(GhMoney::sub($cashNet, $net));
        if (GhMoney::cmp($customerCredit, '0') < 0) {
            $customerCredit = '0.00';
        }

        $reserving = $this->sumReservingRefunds($invoice->id);

        $status = GhInvoicePaymentStatus::UNPAID;
        if (GhMoney::cmp($remainingDue, '0') === 0) {
            $status = GhInvoicePaymentStatus::PAID;
        } elseif (GhMoney::cmp($cashNet, '0') > 0) {
            $status = GhInvoicePaymentStatus::PARTIAL;
        }

        return [
            'invoice_total' => $gross,
            'credits_issued' => $credits,
            'net_invoiced' => $net,
            'confirmed_total' => $confirmed,
            'pending_total' => $pending,
            'refunds_paid_out' => $refundsPaid,
            'payment_reversals' => $reversals,
            'cash_net' => $cashNet,
            'remaining' => $remainingDue,
            'remaining_due' => $remainingDue,
            'customer_credit' => $customerCredit,
            'refundable' => $customerCredit,
            'refunds_reserving' => $reserving,
            'payment_status' => $status,
            'is_paid' => $status === GhInvoicePaymentStatus::PAID,
            'currency' => $invoice->currency ?: 'AED',
        ];
    }

    public function syncInvoicePaymentStatus(GhInvoice $invoice): GhInvoice
    {
        $balance = $this->forInvoice($invoice);
        if ($invoice->payment_status !== $balance['payment_status']) {
            $invoice->payment_status = $balance['payment_status'];
            $invoice->save();
        }

        return $invoice->fresh();
    }

    public function sumConfirmed(int $invoiceId): string
    {
        $raw = GhPayment::query()
            ->where('invoice_id', $invoiceId)
            ->where('status', GhPaymentStatus::CONFIRMED)
            ->sum('amount');

        return GhMoney::of((string) $raw);
    }

    public function sumPending(int $invoiceId): string
    {
        $raw = GhPayment::query()
            ->where('invoice_id', $invoiceId)
            ->whereIn('status', [GhPaymentStatus::PENDING, GhPaymentStatus::PENDING_REVIEW])
            ->sum('amount');

        return GhMoney::of((string) $raw);
    }

    public function sumIssuedCredits(int $invoiceId): string
    {
        $raw = GhCreditNote::query()
            ->where('invoice_id', $invoiceId)
            ->where('status', GhCreditNoteStatus::ISSUED)
            ->sum('total_credit');

        return GhMoney::of((string) $raw);
    }

    public function sumPaidOutRefunds(int $invoiceId): string
    {
        $raw = GhRefund::query()
            ->where('invoice_id', $invoiceId)
            ->where('status', GhRefundStatus::PAID_OUT)
            ->sum('amount');

        return GhMoney::of((string) $raw);
    }

    public function sumReservingRefunds(int $invoiceId): string
    {
        $raw = GhRefund::query()
            ->where('invoice_id', $invoiceId)
            ->whereIn('status', GhRefundStatus::RESERVING)
            ->sum('amount');

        return GhMoney::of((string) $raw);
    }

    public function sumExecutedPaymentReversals(int $invoiceId): string
    {
        $raw = GhFinancialCorrection::query()
            ->where('invoice_id', $invoiceId)
            ->where('correction_type', GhFinancialCorrectionType::PAYMENT_RECORDING_ERROR)
            ->where('status', GhFinancialCorrectionStatus::EXECUTED)
            ->sum('amount');

        return GhMoney::of((string) $raw);
    }

    /** Available for a new refund request (excludes other reserving refunds). */
    public function availableRefundable(GhInvoice $invoice, ?int $excludeRefundId = null): string
    {
        $balance = $this->forInvoice($invoice);
        $reserving = GhRefund::query()
            ->where('invoice_id', $invoice->id)
            ->whereIn('status', GhRefundStatus::RESERVING)
            ->when($excludeRefundId !== null, fn ($q) => $q->where('id', '!=', $excludeRefundId))
            ->sum('amount');
        $reserving = GhMoney::of((string) $reserving);
        $available = GhMoney::of(GhMoney::sub($balance['refundable'], $reserving));

        return GhMoney::cmp($available, '0') < 0 ? '0.00' : $available;
    }
}
