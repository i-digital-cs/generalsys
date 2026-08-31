<?php

namespace App\Services\Gross;

use App\Exceptions\GrossException;
use App\Models\Gross\GrossCustomer;
use App\Models\Gross\GrossInvoiceSequence;
use App\Models\Gross\GrossItem;
use App\Models\Gross\GrossSalesInvoice;
use App\Models\Gross\GrossSalesInvoiceLine;
use App\Models\Gross\GrossShipmentAllocation;
use App\Models\Gross\GrossShippingCompany;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SalesInvoiceService
{
    public function __construct(
        private readonly StockService $stock,
        private readonly AllocationService $allocations,
    ) {}

    public function paginate(int $perPage = 25): LengthAwarePaginator
    {
        return GrossSalesInvoice::query()
            ->with(['customer', 'shippingCompany', 'salesUser'])
            ->orderByDesc('id')
            ->paginate(min($perPage, 50));
    }

    /**
     * Confirmed invoices with shipping fee still PENDING (courier collection queue).
     */
    public function paginatePendingCollections(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $q = GrossSalesInvoice::query()
            ->with(['shippingCompany', 'salesUser', 'customer'])
            ->where('status', GrossSalesInvoice::STATUS_CONFIRMED)
            ->where('shipping_fee_status', GrossSalesInvoice::SHIPPING_FEE_PENDING)
            ->orderByDesc('confirmed_at')
            ->orderByDesc('id');

        if (! empty($filters['shipping_company_id'])) {
            $q->where('shipping_company_id', (int) $filters['shipping_company_id']);
        }

        return $q->paginate(min($perPage, 50))->withQueryString();
    }

    /**
     * Active SALES users for the invoice picker (always include $includeId if set).
     *
     * @return Collection<int, User>
     */
    public function salesPeople(?int $includeId = null): Collection
    {
        return User::query()
            ->where('status', User::STATUS_ACTIVE)
            ->where(function ($q) use ($includeId): void {
                $q->where('role', User::ROLE_SALES);
                if ($includeId !== null) {
                    $q->orWhere('id', $includeId);
                }
            })
            ->orderBy('name_en')
            ->orderBy('name_ar')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $lines
     */
    public function createDraft(User $actor, array $data, array $lines): GrossSalesInvoice
    {
        return DB::transaction(function () use ($actor, $data, $lines) {
            $customer = isset($data['customer_id'])
                ? GrossCustomer::query()->find($data['customer_id'])
                : null;
            $shipping = isset($data['shipping_company_id'])
                ? GrossShippingCompany::query()->find($data['shipping_company_id'])
                : null;

            $invoice = GrossSalesInvoice::query()->create([
                'invoice_number' => $this->nextInvoiceNumber(),
                'customer_id' => $customer?->id,
                'customer_name' => $data['customer_name'] ?? $customer?->name ?? '',
                'customer_phone' => $data['customer_phone'] ?? $customer?->phone ?? '',
                'customer_address' => $data['customer_address'] ?? $customer?->address,
                'sales_user_id' => $data['sales_user_id'] ?? $actor->id,
                'shipping_company_id' => $shipping?->id,
                'shipping_company_name' => $shipping?->name,
                'currency' => $data['currency'],
                'shipping_fee' => $data['shipping_fee'] ?? 0,
                'shipping_fee_status' => ((float) ($data['shipping_fee'] ?? 0)) > 0
                    ? GrossSalesInvoice::SHIPPING_FEE_PENDING
                    : GrossSalesInvoice::SHIPPING_FEE_NONE,
                'discount_amount' => $data['discount_amount'] ?? 0,
                'discount_percent' => $data['discount_percent'] ?? 0,
                'status' => GrossSalesInvoice::STATUS_DRAFT,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
            ]);

            $this->syncLines($invoice, $lines);
            $this->recalculateTotals($invoice);

            return $invoice->fresh(['lines', 'customer', 'shippingCompany']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $lines
     */
    public function updateDraft(GrossSalesInvoice $invoice, array $data, array $lines): GrossSalesInvoice
    {
        if (! $invoice->isDraft()) {
            throw GrossException::notDraft();
        }

        return DB::transaction(function () use ($invoice, $data, $lines) {
            $customer = isset($data['customer_id'])
                ? GrossCustomer::query()->find($data['customer_id'])
                : null;
            $shipping = isset($data['shipping_company_id'])
                ? GrossShippingCompany::query()->find($data['shipping_company_id'])
                : $invoice->shippingCompany;

            if (array_key_exists('shipping_company_id', $data) && $data['shipping_company_id'] === null) {
                $shipping = null;
            }

            $shippingFee = $data['shipping_fee'] ?? $invoice->shipping_fee;

            $invoice->update([
                'customer_id' => array_key_exists('customer_id', $data) ? ($customer?->id) : $invoice->customer_id,
                'customer_name' => $data['customer_name'] ?? $customer?->name ?? $invoice->customer_name,
                'customer_phone' => $data['customer_phone'] ?? $customer?->phone ?? $invoice->customer_phone,
                'customer_address' => array_key_exists('customer_address', $data)
                    ? $data['customer_address']
                    : ($customer?->address ?? $invoice->customer_address),
                'sales_user_id' => $data['sales_user_id'] ?? $invoice->sales_user_id,
                'shipping_company_id' => $shipping?->id,
                'shipping_company_name' => $shipping?->name,
                'currency' => $data['currency'] ?? $invoice->currency,
                'shipping_fee' => $shippingFee,
                'shipping_fee_status' => ((float) $shippingFee) > 0
                    ? GrossSalesInvoice::SHIPPING_FEE_PENDING
                    : GrossSalesInvoice::SHIPPING_FEE_NONE,
                'discount_amount' => $data['discount_amount'] ?? $invoice->discount_amount,
                'discount_percent' => $data['discount_percent'] ?? $invoice->discount_percent,
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $invoice->notes,
            ]);

            $invoice->lines()->delete();
            $this->syncLines($invoice->fresh(), $lines);
            $this->recalculateTotals($invoice->fresh());

            return $invoice->fresh(['lines', 'customer', 'shippingCompany']);
        });
    }

    public function confirm(GrossSalesInvoice $invoice, ?User $actor = null): GrossSalesInvoice
    {
        return DB::transaction(function () use ($invoice, $actor) {
            /** @var GrossSalesInvoice $locked */
            $locked = GrossSalesInvoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === GrossSalesInvoice::STATUS_CONFIRMED
                || $locked->status === GrossSalesInvoice::STATUS_DELIVERED) {
                return $locked->fresh(['lines.allocation', 'lines.item']);
            }

            if ($locked->status !== GrossSalesInvoice::STATUS_DRAFT) {
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

                $this->stock->applySaleOut(
                    $item,
                    (int) $line->qty_pieces,
                    (int) $line->conversion_factor_snapshot,
                    $line->unit,
                    (int) $line->qty,
                    GrossSalesInvoiceLine::class,
                    (int) $line->id,
                    $actor,
                );

                GrossShipmentAllocation::query()->create([
                    'sales_invoice_line_id' => $line->id,
                    'shipping_company_id' => $locked->shipping_company_id,
                    'conversion_factor_snapshot' => $line->conversion_factor_snapshot,
                    'qty_shipped_pieces' => $line->qty_pieces,
                    'qty_delivered_pieces' => 0,
                    'qty_returned_pieces' => 0,
                    'qty_with_courier_pieces' => $line->qty_pieces,
                    'qty_lost_pieces' => 0,
                ]);
            }

            $locked->update([
                'status' => GrossSalesInvoice::STATUS_CONFIRMED,
                'confirmed_at' => now(),
            ]);

            return $locked->fresh(['lines.allocation', 'lines.item']);
        });
    }

    public function cancel(GrossSalesInvoice $invoice): GrossSalesInvoice
    {
        return DB::transaction(function () use ($invoice) {
            /** @var GrossSalesInvoice $locked */
            $locked = GrossSalesInvoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === GrossSalesInvoice::STATUS_CANCELLED) {
                return $locked->fresh(['lines.allocation']);
            }

            if ($locked->status === GrossSalesInvoice::STATUS_DRAFT) {
                $locked->update([
                    'status' => GrossSalesInvoice::STATUS_CANCELLED,
                    'cancelled_at' => now(),
                ]);

                return $locked->fresh(['lines.allocation']);
            }

            if ($locked->status !== GrossSalesInvoice::STATUS_CONFIRMED) {
                throw GrossException::cannotCancel();
            }

            $locked->load(['lines.allocation']);

            foreach ($locked->lines as $line) {
                $alloc = $line->allocation;
                if ($alloc !== null && (int) $alloc->qty_with_courier_pieces > 0) {
                    throw GrossException::cannotCancel();
                }
            }

            $locked->update([
                'status' => GrossSalesInvoice::STATUS_CANCELLED,
                'cancelled_at' => now(),
            ]);

            return $locked->fresh(['lines.allocation']);
        });
    }

    public function refuse(GrossSalesInvoice $invoice, User $actor, ?string $reason = null): GrossSalesInvoice
    {
        return DB::transaction(function () use ($invoice, $actor, $reason) {
            /** @var GrossSalesInvoice $locked */
            $locked = GrossSalesInvoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === GrossSalesInvoice::STATUS_REFUSED) {
                return $locked->fresh(['lines.allocation', 'lines.item']);
            }

            if ($locked->status !== GrossSalesInvoice::STATUS_CONFIRMED) {
                throw GrossException::invalidTransition();
            }

            $locked->load(['lines.item', 'lines.allocation']);

            foreach ($locked->lines as $line) {
                $alloc = $line->allocation;
                if ($alloc === null) {
                    continue;
                }

                /** @var GrossShipmentAllocation $allocLocked */
                $allocLocked = GrossShipmentAllocation::query()
                    ->whereKey($alloc->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $returnPieces = (int) $allocLocked->qty_with_courier_pieces;
                if ($returnPieces < 1) {
                    continue;
                }

                $item = $line->item;
                if ($item === null) {
                    throw GrossException::invalidTransition();
                }

                $this->stock->applyRefuseReturn(
                    $item,
                    $returnPieces,
                    (int) $allocLocked->conversion_factor_snapshot,
                    GrossShipmentAllocation::class,
                    (int) $allocLocked->id,
                    $actor,
                );

                $allocLocked->qty_returned_pieces += $returnPieces;
                $allocLocked->qty_with_courier_pieces = 0;
                $allocLocked->save();
                $allocLocked->assertInvariant();
            }

            $locked->update([
                'status' => GrossSalesInvoice::STATUS_REFUSED,
                'shipping_fee_status' => GrossSalesInvoice::SHIPPING_FEE_LOST,
                'refused_at' => now(),
                'refused_by' => $actor->id,
                'refuse_reason' => $reason,
            ]);

            return $locked->fresh(['lines.allocation', 'lines.item']);
        });
    }

    public function collectShippingFee(GrossSalesInvoice $invoice, User $actor): GrossSalesInvoice
    {
        return DB::transaction(function () use ($invoice, $actor) {
            /** @var GrossSalesInvoice $locked */
            $locked = GrossSalesInvoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($locked->shipping_fee_status === GrossSalesInvoice::SHIPPING_FEE_COLLECTED
                || $locked->shipping_fee_status === GrossSalesInvoice::SHIPPING_FEE_SETTLED) {
                return $locked->fresh(['lines', 'shippingCompany']);
            }

            if ($locked->status !== GrossSalesInvoice::STATUS_CONFIRMED) {
                throw GrossException::notConfirmed();
            }

            if ($locked->shipping_fee_status !== GrossSalesInvoice::SHIPPING_FEE_PENDING) {
                throw GrossException::feeNotPending();
            }

            $locked->update([
                'shipping_fee_status' => GrossSalesInvoice::SHIPPING_FEE_COLLECTED,
                'shipping_fee_collected_at' => now(),
                'shipping_fee_collected_by' => $actor->id,
            ]);

            return $locked->fresh(['lines', 'shippingCompany', 'shippingFeeCollectedByUser']);
        });
    }

    public function markDelivered(GrossSalesInvoice $invoice, ?User $actor = null): GrossSalesInvoice
    {
        return DB::transaction(function () use ($invoice, $actor) {
            /** @var GrossSalesInvoice $locked */
            $locked = GrossSalesInvoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === GrossSalesInvoice::STATUS_DELIVERED) {
                $locked->load(['lines.allocation', 'lines.item']);
                foreach ($locked->lines as $line) {
                    $allocation = $line->allocation;
                    if ($allocation !== null && (int) $allocation->qty_with_courier_pieces > 0) {
                        $this->allocations->confirmDelivered($allocation, $actor);
                    }
                }

                return $locked->fresh(['lines.allocation', 'lines.item', 'shippingCompany']);
            }

            if ($locked->status !== GrossSalesInvoice::STATUS_CONFIRMED) {
                throw GrossException::invalidTransition();
            }

            if (! $locked->canMarkDelivered()) {
                throw GrossException::feeStillPending();
            }

            $locked->load(['lines.allocation', 'lines.item']);

            foreach ($locked->lines as $line) {
                $allocation = $line->allocation;
                if ($allocation !== null) {
                    $this->allocations->confirmDelivered($allocation, $actor);
                }
            }

            $locked->update([
                'status' => GrossSalesInvoice::STATUS_DELIVERED,
                'delivered_at' => now(),
            ]);

            return $locked->fresh(['lines.allocation', 'lines.item', 'shippingCompany']);
        });
    }

    public function recalculateTotals(GrossSalesInvoice $invoice): GrossSalesInvoice
    {
        $invoice->load('lines');
        $subtotal = $invoice->lines->sum(fn (GrossSalesInvoiceLine $line) => (float) $line->line_total);

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

        $total = round($subtotal - $discountAmount + (float) $invoice->shipping_fee, 2);
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
    private function syncLines(GrossSalesInvoice $invoice, array $lines): void
    {
        foreach ($lines as $row) {
            /** @var GrossItem $item */
            $item = GrossItem::query()->findOrFail($row['item_id']);
            $unit = $row['unit'] ?? GrossSalesInvoiceLine::UNIT_PIECE;
            $qty = (int) $row['qty'];
            $factor = UnitConversion::factorFor($unit, (int) $item->pieces_per_carton);
            $qtyPieces = UnitConversion::qtyPieces($qty, $unit, $factor);
            $unitPrice = (float) $row['unit_price'];
            $gross = round($qty * $unitPrice, 2);

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

            GrossSalesInvoiceLine::query()->create([
                'sales_invoice_id' => $invoice->id,
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
                'unit_price' => $unitPrice,
                'discount_amount' => $discountAmount,
                'discount_percent' => $discountPercent,
                'line_total' => $lineTotal,
            ]);
        }
    }

    private function nextInvoiceNumber(): string
    {
        $yearMonth = now()->format('Ym');

        $seq = GrossInvoiceSequence::query()
            ->where('year_month', $yearMonth)
            ->lockForUpdate()
            ->first();

        if ($seq === null) {
            $seq = GrossInvoiceSequence::query()->create([
                'year_month' => $yearMonth,
                'last_number' => 0,
            ]);
            $seq = GrossInvoiceSequence::query()
                ->whereKey($seq->id)
                ->lockForUpdate()
                ->firstOrFail();
        }

        $seq->last_number = (int) $seq->last_number + 1;
        $seq->save();

        return sprintf('MH-%s-%04d', $yearMonth, $seq->last_number);
    }
}
