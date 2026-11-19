<?php

namespace App\Services\GeneralHouse\Payments;

use App\Models\GeneralHouse\GhInvoice;
use App\Models\GeneralHouse\GhOrder;
use App\Support\GeneralHouse\GhCashSettlementStatus;
use App\Support\GeneralHouse\GhCreditNoteStatus;
use App\Support\GeneralHouse\GhDebitAdjustmentStatus;
use App\Support\GeneralHouse\GhPaymentStatus;
use App\Support\GeneralHouse\GhRefundStatus;
use Illuminate\Support\Collection;

/**
 * Manager-only financial view of one order: invoice payment position (GhPaymentBalanceService)
 * plus driver cash custody and settlement (GhCashSettlementQueryService), with a chronological history.
 */
final class GhOrderFinancialSummaryService
{
    public function __construct(
        private readonly GhPaymentBalanceService $balances,
        private readonly GhCashSettlementQueryService $settlement,
    ) {}

    /** @return array<string, mixed> */
    public function forOrder(GhOrder $order): array
    {
        $order->loadMissing([
            'invoice.payments.collector:id,name_ar,name_en',
            'invoice.creditNotes',
            'invoice.debitAdjustments',
            'invoice.refunds',
            'cashCollections.driver:id,name_ar,name_en',
            'cashCollections.allocations.settlement.reversal.reverser:id,name_ar,name_en',
            'cashCollections.allocations.settlement.settler:id,name_ar,name_en',
        ]);

        return $this->compose($order->invoice, $order->cashCollections);
    }

    /** @return array<string, mixed> */
    public function forInvoice(GhInvoice $invoice): array
    {
        $order = $invoice->order;
        if ($order === null) {
            return $this->compose($invoice, collect());
        }
        $order->setRelation('invoice', $invoice);

        return $this->forOrder($order);
    }

    /** @return array<string, mixed> */
    private function compose(?GhInvoice $invoice, Collection $collections): array
    {
        $custody = $this->settlement->summarize($collections);
        $confirmed = $invoice?->payments->where('status', GhPaymentStatus::CONFIRMED) ?? collect();

        return [
            'invoice' => $invoice,
            'balance' => $invoice !== null && $invoice->isIssued() ? $this->balances->forInvoice($invoice) : null,
            'payment_methods' => $confirmed->pluck('method')->unique()->values()->all(),
            'custody' => $custody,
            'drivers' => $collections->pluck('driver')->filter()->unique('id')->values(),
            'last_collected_at' => $collections->max('collected_at'),
            'settlement_status' => $custody['status'] ?? GhCashSettlementStatus::NOT_APPLICABLE,
            'timeline' => $this->timeline($invoice, $collections),
        ];
    }

    /** @return list<array{at: mixed, type: string, reference: ?string, amount: ?string, actor: mixed, settlement_id: ?int, meta: ?string}> */
    private function timeline(?GhInvoice $invoice, Collection $collections): array
    {
        $events = collect();
        $push = fn ($at, string $type, ?string $ref, $amount, $actor = null, ?int $settlementId = null, ?string $meta = null) => $at === null ? null
            : $events->push(['at' => $at, 'type' => $type, 'reference' => $ref, 'amount' => $amount === null ? null : (string) $amount, 'actor' => $actor, 'settlement_id' => $settlementId, 'meta' => $meta]);

        if ($invoice !== null) {
            $push($invoice->issued_at, 'invoice_issued', $invoice->number, $invoice->total_payable);
            foreach ($invoice->payments as $payment) {
                $push($payment->confirmed_at ?? $payment->recorded_at, 'payment_'.strtolower((string) $payment->status), $payment->reference, $payment->amount, $payment->collector, null, (string) $payment->method);
            }
            foreach ($invoice->creditNotes->where('status', GhCreditNoteStatus::ISSUED) as $note) {
                $push($note->issued_at, 'credit_note_issued', $note->number, $note->total_credit);
            }
            foreach ($invoice->debitAdjustments->where('status', GhDebitAdjustmentStatus::ISSUED) as $debit) {
                $push($debit->issued_at, 'debit_adjustment_issued', $debit->number, $debit->total_debit);
            }
            foreach ($invoice->refunds->where('status', GhRefundStatus::PAID_OUT) as $refund) {
                $push($refund->paid_out_at, 'refund_paid_out', $refund->reference, $refund->amount);
            }
        }
        foreach ($collections as $collection) {
            $push($collection->collected_at, 'cash_collected', $collection->reference, $collection->amount, $collection->driver);
            foreach ($collection->allocations as $allocation) {
                $settlement = $allocation->settlement;
                $push($allocation->allocated_at, 'cash_settled', $settlement?->reference, $allocation->amount, $settlement?->settler, $settlement?->id);
                if ($settlement?->reversal !== null) {
                    $push($settlement->reversal->reversed_at, 'settlement_reversed', $settlement->reversal->reference, $allocation->amount, $settlement->reversal->reverser, $settlement->id, $settlement->reversal->reason);
                }
            }
        }

        return $events->sortBy(fn (array $e) => $e['at']->getTimestamp())->values()->all();
    }
}
