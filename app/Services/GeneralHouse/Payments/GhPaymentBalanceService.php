<?php

namespace App\Services\GeneralHouse\Payments;

use App\Models\GeneralHouse\GhInvoice;
use App\Models\GeneralHouse\GhPayment;
use App\Support\GeneralHouse\GhInvoicePaymentStatus;
use App\Support\GeneralHouse\GhMoney;
use App\Support\GeneralHouse\GhPaymentStatus;

/**
 * Single source of truth for GH invoice payment balances.
 * Controllers / Blade / JS are consumers only.
 */
class GhPaymentBalanceService
{
    /**
     * @return array{
     *   invoice_total: string,
     *   confirmed_total: string,
     *   pending_total: string,
     *   remaining: string,
     *   payment_status: string,
     *   is_paid: bool,
     *   currency: string,
     * }
     */
    public function forInvoice(GhInvoice $invoice): array
    {
        $total = GhMoney::of((string) $invoice->total_payable);
        $confirmed = $this->sumConfirmed($invoice->id);
        $pending = $this->sumPending($invoice->id);
        $remaining = GhMoney::roundHalfUp(GhMoney::sub($total, $confirmed));
        if (GhMoney::cmp($remaining, '0') < 0) {
            $remaining = '0.00';
        }

        $status = GhInvoicePaymentStatus::UNPAID;
        if (GhMoney::cmp($total, '0') === 0 || GhMoney::cmp($confirmed, $total) >= 0) {
            $status = GhInvoicePaymentStatus::PAID;
            $remaining = '0.00';
        } elseif (GhMoney::cmp($confirmed, '0') > 0) {
            $status = GhInvoicePaymentStatus::PARTIAL;
        }

        return [
            'invoice_total' => $total,
            'confirmed_total' => $confirmed,
            'pending_total' => $pending,
            'remaining' => $remaining,
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
}
