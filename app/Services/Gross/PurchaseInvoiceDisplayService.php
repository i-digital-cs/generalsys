<?php

namespace App\Services\Gross;

use App\Models\Gross\GrossPurchaseInvoice;
use App\Models\Gross\GrossPurchaseInvoiceLine;

/**
 * Read-only view DTO for purchase invoice show / export (net after returns).
 */
class PurchaseInvoiceDisplayService
{
    /**
     * @return array{
     *     has_returns: bool,
     *     lines: list<array{
     *         line: GrossPurchaseInvoiceLine,
     *         qty_purchased: int,
     *         qty_returned: int,
     *         qty_remaining: int,
     *         original_line_total: float,
     *         returned_amount: float,
     *         net_line_total: float
     *     }>,
     *     returned_merchandise: float,
     *     net_subtotal: float,
     *     net_discount: float,
     *     net_grand_total: float,
     *     original_total: float
     * }
     */
    public function build(GrossPurchaseInvoice $invoice): array
    {
        $invoice->loadMissing('lines');

        $lines = [];
        $returnedMerchandise = 0.0;
        $netSubtotal = 0.0;
        $hasReturns = false;

        foreach ($invoice->lines as $line) {
            $amounts = $this->lineAmounts($line);
            if ($amounts['qty_returned'] > 0) {
                $hasReturns = true;
            }
            $returnedMerchandise += $amounts['returned_amount'];
            $netSubtotal += $amounts['net_line_total'];

            $lines[] = [
                'line' => $line,
                'qty_purchased' => (int) $line->qty_pieces,
                'qty_returned' => $amounts['qty_returned'],
                'qty_remaining' => $amounts['qty_remaining'],
                'original_line_total' => (float) $line->line_total,
                'returned_amount' => $amounts['returned_amount'],
                'net_line_total' => $amounts['net_line_total'],
            ];
        }

        $originalSubtotal = (float) $invoice->subtotal;
        $netDiscount = $this->netInvoiceDiscount($invoice, $originalSubtotal, $netSubtotal);
        $netGrandTotal = round(max(0, $netSubtotal - $netDiscount), 2);

        return [
            'has_returns' => $hasReturns && ! $invoice->isDraft(),
            'lines' => $lines,
            'returned_merchandise' => round($returnedMerchandise, 2),
            'net_subtotal' => round($netSubtotal, 2),
            'net_discount' => $netDiscount,
            'net_grand_total' => $netGrandTotal,
            'original_total' => (float) $invoice->total,
        ];
    }

    /**
     * @return array{qty_returned: int, qty_remaining: int, returned_amount: float, net_line_total: float}
     */
    private function lineAmounts(GrossPurchaseInvoiceLine $line): array
    {
        $lineTotal = (float) $line->line_total;
        $qtyPieces = (int) $line->qty_pieces;
        $qtyReturned = (int) $line->qty_returned_pieces;
        $qtyRemaining = max(0, $qtyPieces - $qtyReturned);

        $returnedAmount = $qtyPieces > 0
            ? round($lineTotal * ($qtyReturned / $qtyPieces), 2)
            : 0.0;
        $netLineTotal = round(max(0, $lineTotal - $returnedAmount), 2);

        return [
            'qty_returned' => $qtyReturned,
            'qty_remaining' => $qtyRemaining,
            'returned_amount' => $returnedAmount,
            'net_line_total' => $netLineTotal,
        ];
    }

    private function netInvoiceDiscount(GrossPurchaseInvoice $invoice, float $originalSubtotal, float $netSubtotal): float
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
}
