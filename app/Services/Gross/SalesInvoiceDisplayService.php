<?php

namespace App\Services\Gross;

use App\Models\Gross\GrossSalesInvoice;
use App\Models\Gross\GrossSalesInvoiceLine;

/**
 * Read-only view DTO for sales invoice show (values come from persisted net columns + allocations).
 */
class SalesInvoiceDisplayService
{
    public function __construct(
        private readonly PieceLocationService $pieceLocations,
    ) {}

    /**
     * @return array{
     *     has_tracking: bool,
     *     lines: list<array{
     *         line: GrossSalesInvoiceLine,
     *         qty_shipped: int,
     *         qty_returned: int,
     *         qty_with_courier: int,
     *         qty_delivered: int,
     *         qty_lost: int,
     *         original_line_total: float,
     *         returned_amount: float,
     *         lost_amount: float,
     *         net_line_total: float,
     *         stock: array{
     *             on_hand: int,
     *             with_courier: int,
     *             lost: int,
     *             total_tracked: int,
     *             on_invoice_with_courier: int,
     *             on_invoice_returned: int,
     *             on_invoice_delivered: int
     *         }|null
     *     }>,
     *     piece_summary: array{
     *         total_on_invoice: int,
     *         total_returned: int,
     *         total_with_courier: int,
     *         total_delivered: int,
     *         total_lost: int
     *     },
     *     returned_merchandise: float,
     *     net_subtotal: float,
     *     net_discount: float,
     *     net_before_shipping: float,
     *     net_grand_total: float,
     *     original_total: float
     * }
     */
    public function build(GrossSalesInvoice $invoice): array
    {
        $invoice->load(['lines.allocation', 'lines.item.balance']);
        $hasTracking = ! $invoice->isDraft();

        $lines = [];
        $pieceSummary = [
            'total_on_invoice' => 0,
            'total_returned' => 0,
            'total_returned_with_courier' => 0,
            'total_awaiting_delivery' => 0,
            'total_with_courier' => 0,
            'total_delivered' => 0,
            'total_lost' => 0,
        ];

        foreach ($invoice->lines as $line) {
            $allocation = $line->allocation;
            $qtyShipped = (int) $line->qty_pieces;
            $qtyReturned = $allocation ? (int) $allocation->qty_returned_pieces : 0;
            $qtyWithCourier = $allocation ? (int) $allocation->qty_with_courier_pieces : 0;
            $qtyDelivered = $allocation ? (int) $allocation->qty_delivered_pieces : 0;
            $qtyLost = $allocation ? (int) $allocation->qty_lost_pieces : 0;
            $qtyReturnedReceived = $allocation ? (int) $allocation->qty_returned_received_pieces : 0;
            $qtyReturnedWithCourier = $allocation ? $allocation->returnedPendingHandover() : 0;
            $qtyAwaitingDelivery = $allocation ? $allocation->deliverableWithCourier() : 0;

            $pieceSummary['total_on_invoice'] += $qtyShipped;
            $pieceSummary['total_returned'] += $qtyReturned;
            $pieceSummary['total_returned_with_courier'] += $qtyReturnedWithCourier;
            $pieceSummary['total_awaiting_delivery'] += $qtyAwaitingDelivery;
            $pieceSummary['total_with_courier'] += $qtyWithCourier;
            $pieceSummary['total_delivered'] += $qtyDelivered;
            $pieceSummary['total_lost'] += $qtyLost;

            $stock = null;
            if ($hasTracking && $line->item_id !== null) {
                $balance = $line->item?->balance;
                $onHand = (int) ($balance?->qty_on_hand_pieces ?? 0);
                $withCourier = (int) ($balance?->qty_with_courier_pieces ?? 0);
                $lost = (int) ($balance?->qty_lost_pieces ?? 0);

                $stock = [
                    'on_hand' => $onHand,
                    'with_courier' => $withCourier,
                    'lost' => $lost,
                    'total_tracked' => $this->pieceLocations->totalTrackedPieces($balance),
                    'on_invoice_with_courier' => $qtyWithCourier,
                    'on_invoice_returned' => $qtyReturned,
                    'on_invoice_delivered' => $qtyDelivered,
                ];
            }

            $lines[] = [
                'line' => $line,
                'qty_shipped' => $qtyShipped,
                'qty_returned' => $qtyReturned,
                'qty_with_courier' => $qtyWithCourier,
                'qty_delivered' => $qtyDelivered,
                'qty_lost' => $qtyLost,
                'qty_returned_with_courier' => $qtyReturnedWithCourier,
                'qty_returned_received' => $qtyReturnedReceived,
                'qty_awaiting_delivery' => $qtyAwaitingDelivery,
                'original_line_total' => (float) $line->line_total,
                'returned_amount' => (float) $line->returned_amount,
                'lost_amount' => (float) $line->lost_amount,
                'net_line_total' => (float) $line->net_line_total,
                'stock' => $stock,
            ];
        }

        $netSubtotal = (float) $invoice->net_subtotal;
        $netDiscount = (float) $invoice->net_discount;
        $netBeforeShipping = round(max(0, $netSubtotal - $netDiscount), 2);

        return [
            'has_tracking' => $hasTracking && collect($lines)->contains(fn (array $row): bool => $row['qty_shipped'] > 0),
            'lines' => $lines,
            'piece_summary' => $pieceSummary,
            'refused_progress' => $this->refusedProgress($invoice, $pieceSummary),
            'returned_merchandise' => (float) $invoice->returned_merchandise,
            'net_subtotal' => $netSubtotal,
            'net_discount' => $netDiscount,
            'net_before_shipping' => $netBeforeShipping,
            'merchandise_collectible' => $invoice->courierMerchandiseAmount(),
            'net_grand_total' => (float) $invoice->net_total,
            'original_total' => (float) $invoice->total,
            'net_shipping_fee' => round((float) $invoice->net_total - $netBeforeShipping, 2),
        ];
    }

    /**
     * @param  array<string, int>  $pieceSummary
     * @return array{
     *     is_refused: bool,
     *     pending_handover: int,
     *     handover_complete: bool,
     *     delivery_fee_owed: float,
     *     steps: list<array{key: string, done: bool, label_key: string}>
     * }|null
     */
    private function refusedProgress(GrossSalesInvoice $invoice, array $pieceSummary): ?array
    {
        if (! $invoice->isRefused()) {
            return null;
        }

        $pending = (int) $invoice->refusedPiecesPendingHandover();
        $handoverComplete = $pending < 1;

        return [
            'is_refused' => true,
            'pending_handover' => $pending,
            'handover_complete' => $handoverComplete,
            'delivery_fee_owed' => $invoice->deliveryFeeOwed(),
            'steps' => [
                ['key' => 'refused', 'done' => true, 'current' => false, 'label_key' => 'gross.sales.refused_progress.refused'],
                ['key' => 'with_courier', 'done' => $pending > 0, 'current' => $pending > 0, 'label_key' => 'gross.sales.refused_progress.with_courier'],
                ['key' => 'received', 'done' => $handoverComplete, 'current' => false, 'label_key' => 'gross.sales.refused_progress.received'],
            ],
        ];
    }
}
