<?php

namespace App\Services\Gross;

use App\Models\Gross\GrossSalesInvoice;
use App\Models\Gross\GrossSalesInvoiceLine;

/**
 * Persists net merchandise + collectible totals on sales invoices (single source of truth with allocations).
 */
class SalesInvoiceNetService
{
    public function recalculateInvoice(GrossSalesInvoice $invoice): GrossSalesInvoice
    {
        $invoice->load('lines.allocation');

        $returnedMerchandise = 0.0;
        $netSubtotal = 0.0;

        foreach ($invoice->lines as $line) {
            $amounts = $this->recalculateLine($line);
            $returnedMerchandise += $amounts['returned_amount'];
            $netSubtotal += $amounts['net_line_total'];
        }

        $originalSubtotal = (float) $invoice->subtotal;
        $netDiscount = $this->netInvoiceDiscount($invoice, $originalSubtotal, $netSubtotal);
        $netBeforeShipping = round(max(0, $netSubtotal - $netDiscount), 2);
        $netShipping = $this->netShippingFee($invoice);
        $netTotal = round(max(0, $netBeforeShipping + $netShipping), 2);

        $invoice->update([
            'returned_merchandise' => round($returnedMerchandise, 2),
            'net_subtotal' => round($netSubtotal, 2),
            'net_discount' => $netDiscount,
            'net_total' => $netTotal,
        ]);

        return $invoice->refresh();
    }

    /**
     * @return array{returned_amount: float, lost_amount: float, net_line_total: float}
     */
    public function recalculateLine(GrossSalesInvoiceLine $line): array
    {
        $lineTotal = (float) $line->line_total;
        $qtyPieces = (int) $line->qty_pieces;
        $allocation = $line->relationLoaded('allocation') ? $line->allocation : $line->allocation()->first();

        if ($qtyPieces < 1 || $allocation === null) {
            $line->update([
                'returned_amount' => 0,
                'lost_amount' => 0,
                'net_line_total' => round($lineTotal, 2),
            ]);

            return [
                'returned_amount' => 0.0,
                'lost_amount' => 0.0,
                'net_line_total' => round($lineTotal, 2),
            ];
        }

        $returnedPieces = (int) $allocation->qty_returned_pieces;
        $lostPieces = (int) $allocation->qty_lost_pieces;

        $returnedAmount = round($lineTotal * ($returnedPieces / $qtyPieces), 2);
        $lostAmount = round($lineTotal * ($lostPieces / $qtyPieces), 2);
        $netLineTotal = round(max(0, $lineTotal - $returnedAmount - $lostAmount), 2);

        $line->update([
            'returned_amount' => $returnedAmount,
            'lost_amount' => $lostAmount,
            'net_line_total' => $netLineTotal,
        ]);

        return [
            'returned_amount' => $returnedAmount,
            'lost_amount' => $lostAmount,
            'net_line_total' => $netLineTotal,
        ];
    }

    private function netInvoiceDiscount(GrossSalesInvoice $invoice, float $originalSubtotal, float $netSubtotal): float
    {
        if ($netSubtotal <= 0 || $originalSubtotal <= 0) {
            return 0.0;
        }

        $percent = (float) $invoice->discount_percent;
        if ($percent > 0) {
            return round($netSubtotal * ($percent / 100), 2);
        }

        $amount = (float) $invoice->discount_amount;
        if ($amount <= 0) {
            return 0.0;
        }

        return round($amount * ($netSubtotal / $originalSubtotal), 2);
    }

    private function netShippingFee(GrossSalesInvoice $invoice): float
    {
        if ($invoice->status === GrossSalesInvoice::STATUS_REFUSED) {
            return 0.0;
        }

        return (float) $invoice->shipping_fee;
    }
}
