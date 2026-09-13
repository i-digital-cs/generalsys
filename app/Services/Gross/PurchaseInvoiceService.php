<?php

namespace App\Services\Gross;

use App\Exceptions\GrossException;
use App\Models\Gross\GrossItem;
use App\Models\Gross\GrossPurchaseInvoice;
use App\Models\Gross\GrossPurchaseInvoiceLine;
use App\Models\Gross\GrossPurchaseInvoiceSequence;
use App\Models\Gross\GrossSupplier;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PurchaseInvoiceService
{
    public function __construct(
        private readonly StockService $stock,
        private readonly SupplierService $suppliers,
    ) {}

    public function paginate(int $perPage = 25): LengthAwarePaginator
    {
        return GrossPurchaseInvoice::query()
            ->with(['supplier', 'creator'])
            ->orderByDesc('id')
            ->paginate(min($perPage, 50));
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $lines
     */
    public function createDraft(User $actor, array $data, array $lines): GrossPurchaseInvoice
    {
        return DB::transaction(function () use ($actor, $data, $lines) {
            $supplier = $this->resolveSupplier($data);

            $invoice = GrossPurchaseInvoice::query()->create([
                'invoice_number' => $this->nextInvoiceNumber(),
                'supplier_id' => $supplier?->id,
                'supplier_name' => $data['supplier_name'] ?? $supplier?->name ?? '',
                'supplier_phone' => $data['supplier_phone'] ?? $supplier?->phone ?? '',
                'supplier_address' => $data['supplier_address'] ?? $supplier?->address,
                'currency' => $data['currency'],
                'discount_amount' => $data['discount_amount'] ?? 0,
                'discount_percent' => $data['discount_percent'] ?? 0,
                'status' => GrossPurchaseInvoice::STATUS_DRAFT,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
            ]);

            $this->syncLines($invoice, $lines);
            $this->recalculateTotals($invoice);

            return $invoice->fresh(['lines', 'supplier']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $lines
     */
    public function updateDraft(GrossPurchaseInvoice $invoice, array $data, array $lines): GrossPurchaseInvoice
    {
        if (! $invoice->isDraft()) {
            throw GrossException::notDraft();
        }

        return DB::transaction(function () use ($invoice, $data, $lines) {
            $supplier = $this->resolveSupplier($data);

            $invoice->update([
                'supplier_id' => $supplier?->id,
                'supplier_name' => $data['supplier_name'] ?? $supplier?->name ?? $invoice->supplier_name,
                'supplier_phone' => $data['supplier_phone'] ?? $supplier?->phone ?? $invoice->supplier_phone,
                'supplier_address' => array_key_exists('supplier_address', $data)
                    ? $data['supplier_address']
                    : ($supplier?->address ?? $invoice->supplier_address),
                'currency' => $data['currency'] ?? $invoice->currency,
                'discount_amount' => $data['discount_amount'] ?? $invoice->discount_amount,
                'discount_percent' => $data['discount_percent'] ?? $invoice->discount_percent,
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $invoice->notes,
            ]);

            $invoice->lines()->delete();
            $this->syncLines($invoice->fresh(), $lines);
            $this->recalculateTotals($invoice->fresh());

            return $invoice->fresh(['lines', 'supplier']);
        });
    }

    public function confirm(GrossPurchaseInvoice $invoice, ?User $actor = null): GrossPurchaseInvoice
    {
        return DB::transaction(function () use ($invoice, $actor) {
            /** @var GrossPurchaseInvoice $locked */
            $locked = GrossPurchaseInvoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === GrossPurchaseInvoice::STATUS_CONFIRMED) {
                return $locked->fresh(['lines.item']);
            }

            if ($locked->status !== GrossPurchaseInvoice::STATUS_DRAFT) {
                throw GrossException::invalidTransition();
            }

            $locked->load(['lines.item']);

            if ($locked->lines->isEmpty()) {
                throw GrossException::invalidQty();
            }

            foreach ($locked->lines as $line) {
                $item = $line->item;
                if ($item === null) {
                    throw GrossException::invalidTransition();
                }

                $this->stock->applyPurchaseIn(
                    $item,
                    (int) $line->qty_pieces,
                    (int) $line->conversion_factor_snapshot,
                    $line->unit,
                    (int) $line->qty,
                    GrossPurchaseInvoiceLine::class,
                    (int) $line->id,
                    $actor,
                );

                $salePrice = (float) $line->sale_price;
                if ($salePrice > 0 && (float) $item->default_sale_price !== $salePrice) {
                    $item->update(['default_sale_price' => $salePrice]);
                }
            }

            $locked->update([
                'status' => GrossPurchaseInvoice::STATUS_CONFIRMED,
                'confirmed_at' => now(),
            ]);

            return $locked->fresh(['lines.item']);
        });
    }

    public function cancel(GrossPurchaseInvoice $invoice): GrossPurchaseInvoice
    {
        return DB::transaction(function () use ($invoice) {
            /** @var GrossPurchaseInvoice $locked */
            $locked = GrossPurchaseInvoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === GrossPurchaseInvoice::STATUS_CANCELLED) {
                return $locked->fresh(['lines']);
            }

            if ($locked->status !== GrossPurchaseInvoice::STATUS_DRAFT) {
                throw GrossException::cannotCancel();
            }

            $locked->update([
                'status' => GrossPurchaseInvoice::STATUS_CANCELLED,
                'cancelled_at' => now(),
            ]);

            return $locked->fresh(['lines']);
        });
    }

    public function recalculateTotals(GrossPurchaseInvoice $invoice): GrossPurchaseInvoice
    {
        $invoice->load('lines');
        $subtotal = $invoice->lines->sum(fn (GrossPurchaseInvoiceLine $line) => (float) $line->line_total);

        $discountPercent = (float) $invoice->discount_percent;
        $discountAmount = (float) $invoice->discount_amount;

        if ($discountPercent > 0) {
            $discountAmount = round($subtotal * ($discountPercent / 100), 2);
        } else {
            $discountPercent = 0;
        }

        if ($discountAmount > $subtotal) {
            $discountAmount = $subtotal;
        }

        $total = round($subtotal - $discountAmount, 2);
        if ($total < 0) {
            $total = 0;
        }

        $invoice->update([
            'subtotal' => round($subtotal, 2),
            'discount_amount' => $discountAmount,
            'discount_percent' => $discountPercent,
            'total' => $total,
        ]);

        return $invoice->refresh();
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function syncLines(GrossPurchaseInvoice $invoice, array $lines): void
    {
        foreach ($lines as $row) {
            /** @var GrossItem $item */
            $item = GrossItem::query()->findOrFail($row['item_id']);
            $unit = $row['unit'] ?? GrossPurchaseInvoiceLine::UNIT_PIECE;
            $qty = (int) $row['qty'];
            $factor = UnitConversion::factorFor($unit, (int) $item->pieces_per_carton);
            $qtyPieces = UnitConversion::qtyPieces($qty, $unit, $factor);
            $buyPrice = (float) $row['buy_price'];
            $salePrice = (float) ($row['sale_price'] ?? $item->default_sale_price ?? 0);
            $gross = round($qty * $buyPrice, 2);

            $discountPercent = (float) ($row['discount_percent'] ?? 0);
            $discountAmount = (float) ($row['discount_amount'] ?? 0);

            if ($discountPercent > 0) {
                $discountAmount = round($gross * ($discountPercent / 100), 2);
            } else {
                $discountPercent = 0;
            }

            if ($discountAmount > $gross) {
                $discountAmount = $gross;
            }

            $lineTotal = round($gross - $discountAmount, 2);

            GrossPurchaseInvoiceLine::query()->create([
                'purchase_invoice_id' => $invoice->id,
                'item_id' => $item->id,
                'item_code_snapshot' => $item->code,
                'item_name_snapshot' => $item->name,
                'color_snapshot' => $item->color,
                'size_snapshot' => $item->size,
                'model_snapshot' => $item->model,
                'qty' => $qty,
                'unit' => $unit,
                'conversion_factor_snapshot' => $factor,
                'qty_pieces' => $qtyPieces,
                'buy_price' => $buyPrice,
                'sale_price' => $salePrice,
                'discount_amount' => $discountAmount,
                'discount_percent' => $discountPercent,
                'line_total' => $lineTotal,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveSupplier(array $data): ?GrossSupplier
    {
        $name = trim((string) ($data['supplier_name'] ?? ''));
        $phone = trim((string) ($data['supplier_phone'] ?? ''));
        $address = $data['supplier_address'] ?? null;

        if ($name !== '' && $phone !== '') {
            return $this->suppliers->findOrCreateFromInvoice(
                $name,
                $phone,
                is_string($address) ? $address : null,
            );
        }

        if (! empty($data['supplier_id'])) {
            return GrossSupplier::query()->find($data['supplier_id']);
        }

        return null;
    }

    private function nextInvoiceNumber(): string
    {
        $yearMonth = now()->format('Ym');

        $seq = GrossPurchaseInvoiceSequence::query()
            ->where('year_month', $yearMonth)
            ->lockForUpdate()
            ->first();

        if ($seq === null) {
            $seq = GrossPurchaseInvoiceSequence::query()->create([
                'year_month' => $yearMonth,
                'last_number' => 0,
            ]);
            $seq = GrossPurchaseInvoiceSequence::query()
                ->whereKey($seq->id)
                ->lockForUpdate()
                ->firstOrFail();
        }

        $seq->last_number = (int) $seq->last_number + 1;
        $seq->save();

        return sprintf('MP-%s-%04d', $yearMonth, $seq->last_number);
    }
}
