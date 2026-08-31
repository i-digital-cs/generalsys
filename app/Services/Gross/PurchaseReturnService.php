<?php

namespace App\Services\Gross;

use App\Exceptions\GrossException;
use App\Models\Gross\GrossPurchaseInvoice;
use App\Models\Gross\GrossPurchaseInvoiceLine;
use App\Models\Gross\GrossPurchaseReturn;
use App\Models\Gross\GrossPurchaseReturnLine;
use App\Models\Gross\GrossPurchaseReturnSequence;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PurchaseReturnService
{
    public function __construct(private readonly StockService $stock) {}

    /**
     * Partial return against a confirmed purchase invoice.
     *
     * @param  list<array{purchase_invoice_line_id: int, qty: int}>  $lines
     */
    public function create(User $actor, GrossPurchaseInvoice $invoice, array $lines, ?string $notes = null): GrossPurchaseReturn
    {
        return DB::transaction(function () use ($actor, $invoice, $lines, $notes) {
            /** @var GrossPurchaseInvoice $locked */
            $locked = GrossPurchaseInvoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== GrossPurchaseInvoice::STATUS_CONFIRMED) {
                throw GrossException::notConfirmed();
            }

            if ($lines === []) {
                throw GrossException::invalidQty();
            }

            $return = GrossPurchaseReturn::query()->create([
                'return_number' => $this->nextReturnNumber(),
                'purchase_invoice_id' => $locked->id,
                'notes' => $notes,
                'created_by' => $actor->id,
            ]);

            foreach ($lines as $row) {
                $this->applyOneLine($return, $locked, $row, $actor);
            }

            return $return->fresh(['lines.invoiceLine', 'lines.item']);
        });
    }

    /**
     * @param  array{purchase_invoice_line_id: int, qty: int}  $row
     */
    private function applyOneLine(
        GrossPurchaseReturn $return,
        GrossPurchaseInvoice $invoice,
        array $row,
        User $actor,
    ): void {
        /** @var GrossPurchaseInvoiceLine $line */
        $line = GrossPurchaseInvoiceLine::query()
            ->whereKey((int) $row['purchase_invoice_line_id'])
            ->where('purchase_invoice_id', $invoice->id)
            ->lockForUpdate()
            ->firstOrFail();

        $qty = (int) $row['qty'];
        if ($qty < 1) {
            throw GrossException::invalidQty();
        }

        $factor = (int) $line->conversion_factor_snapshot;
        $qtyPieces = $qty * $factor;
        $remaining = $line->remainingPieces();

        if ($qtyPieces > $remaining) {
            throw GrossException::purchaseReturnQty();
        }

        $item = $line->item;
        if ($item === null) {
            throw GrossException::invalidTransition();
        }

        $buyPrice = (float) $line->buy_price;
        $lineTotal = round($qty * $buyPrice, 2);

        $returnLine = GrossPurchaseReturnLine::query()->create([
            'purchase_return_id' => $return->id,
            'purchase_invoice_line_id' => $line->id,
            'item_id' => $item->id,
            'qty' => $qty,
            'unit' => $line->unit,
            'conversion_factor' => $factor,
            'qty_pieces' => $qtyPieces,
            'buy_price' => $buyPrice,
            'line_total' => $lineTotal,
        ]);

        $this->stock->applyPurchaseReturn(
            $item,
            $qtyPieces,
            $factor,
            $line->unit,
            $qty,
            GrossPurchaseReturnLine::class,
            (int) $returnLine->id,
            $actor,
        );

        $line->qty_returned_pieces = (int) $line->qty_returned_pieces + $qtyPieces;
        $line->save();
    }

    private function nextReturnNumber(): string
    {
        $yearMonth = now()->format('Ym');

        $seq = GrossPurchaseReturnSequence::query()
            ->where('year_month', $yearMonth)
            ->lockForUpdate()
            ->first();

        if ($seq === null) {
            $seq = GrossPurchaseReturnSequence::query()->create([
                'year_month' => $yearMonth,
                'last_number' => 0,
            ]);
            $seq = GrossPurchaseReturnSequence::query()
                ->whereKey($seq->id)
                ->lockForUpdate()
                ->firstOrFail();
        }

        $seq->last_number = (int) $seq->last_number + 1;
        $seq->save();

        return sprintf('MR-%s-%04d', $yearMonth, $seq->last_number);
    }
}
