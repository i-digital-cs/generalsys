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
use App\Models\Gross\GrossStockMovement;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Sales invoice orchestration façade.
 * Fulfillment pipeline → {@see SalesInvoiceFulfillmentService}.
 * COD collection → {@see SalesInvoiceCodCollectionService}.
 * Stock mutations → {@see StockService} only.
 */
class SalesInvoiceService
{
    public function __construct(
        private readonly StockService $stock,
        private readonly AllocationService $allocations,
        private readonly SalesInvoiceNetService $net,
        private readonly CustomerService $customers,
        private readonly ShippingCompanyService $shippingCompanies,
        private readonly SalesInvoiceFulfillmentService $fulfillment,
        private readonly SalesInvoiceCodCollectionService $cod,
    ) {}

    public function paginate(int $perPage = 25): LengthAwarePaginator
    {
        return GrossSalesInvoice::query()
            ->with(['customer', 'shippingCompany', 'salesUser'])
            ->orderByDesc('id')
            ->paginate(min($perPage, 50));
    }

    /**
     * Confirmed invoices with COD still PENDING (courier collection queue).
     * Only after stock is with the courier.
     */
    public function paginatePendingCollections(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return $this->cod->paginatePendingCollections($filters, $perPage);
    }

    /**
     * Sidebar queues for مسار الشحنة.
     *
     * @param  'preparing'|'ready'|'with_courier'|'delivered'|'returned'  $queue
     */
    public function paginateFulfillmentQueue(string $queue, int $perPage = 25): LengthAwarePaginator
    {
        return $this->fulfillment->paginateFulfillmentQueue($queue, $perPage);
    }

    /**
     * @return Collection<int, GrossSalesInvoice>
     */
    public function listCollectedWithoutTreasury(int $limit = 100): Collection
    {
        return $this->cod->listCollectedWithoutTreasury($limit);
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
            $customer = $this->resolveCustomer($data);
            $shipping = $this->resolveShipping($data);

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
                'shipping_fee_status' => GrossSalesInvoice::SHIPPING_FEE_NONE,
                'cod_collection_status' => GrossSalesInvoice::COD_NONE,
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
     * Admin update — any status. Unwinds courier/delivery/loss stock, resets to CONFIRMED+PREPARING
     * when previously past draft (except CANCELLED stays cancelled).
     *
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $lines
     */
    public function update(GrossSalesInvoice $invoice, array $data, array $lines, ?User $actor = null): GrossSalesInvoice
    {
        return DB::transaction(function () use ($invoice, $data, $lines, $actor) {
            /** @var GrossSalesInvoice $locked */
            $locked = GrossSalesInvoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            $priorStatus = $locked->status;

            $this->unwindFulfillmentStock($locked, $actor);
            $this->clearPackingImage($locked);
            $this->deleteCourierCodVoucher($locked);

            $customer = $this->resolveCustomer($data);
            $shipping = $this->resolveShipping($data, $locked->shippingCompany);
            $shippingFee = $data['shipping_fee'] ?? $locked->shipping_fee;

            // GROSS_MODULE: currency immutable after CONFIRMED (Admin mutate may edit other fields).
            $nextCurrency = $data['currency'] ?? $locked->currency;
            if ($priorStatus !== GrossSalesInvoice::STATUS_DRAFT
                && strtoupper((string) $nextCurrency) !== strtoupper((string) $locked->currency)) {
                throw GrossException::currencyImmutable();
            }

            $locked->update(array_merge([
                'customer_id' => $customer?->id,
                'customer_name' => $data['customer_name'] ?? $customer?->name ?? $locked->customer_name,
                'customer_phone' => $data['customer_phone'] ?? $customer?->phone ?? $locked->customer_phone,
                'customer_address' => array_key_exists('customer_address', $data)
                    ? $data['customer_address']
                    : ($customer?->address ?? $locked->customer_address),
                'sales_user_id' => $data['sales_user_id'] ?? $locked->sales_user_id,
                'shipping_company_id' => $shipping?->id,
                'shipping_company_name' => $shipping?->name,
                'currency' => $priorStatus === GrossSalesInvoice::STATUS_DRAFT
                    ? $nextCurrency
                    : $locked->currency,
                'shipping_fee' => $shippingFee,
                'discount_amount' => $data['discount_amount'] ?? $locked->discount_amount,
                'discount_percent' => $data['discount_percent'] ?? $locked->discount_percent,
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $locked->notes,
            ], $this->resetFulfillmentAttributes($priorStatus)));

            $locked->lines()->delete();
            $this->syncLines($locked->fresh(), $lines);
            $this->recalculateTotals($locked->fresh());

            if ($priorStatus !== GrossSalesInvoice::STATUS_DRAFT
                && $priorStatus !== GrossSalesInvoice::STATUS_CANCELLED) {
                $this->net->recalculateInvoice($locked->fresh(['lines.allocation']));
            }

            return $locked->fresh(['lines', 'customer', 'shippingCompany']);
        });
    }

    /** @deprecated Use update() */
    public function updateDraft(GrossSalesInvoice $invoice, array $data, array $lines): GrossSalesInvoice
    {
        return $this->update($invoice, $data, $lines);
    }

    public function confirm(GrossSalesInvoice $invoice, ?User $actor = null): GrossSalesInvoice
    {
        return $this->fulfillment->confirm($invoice, $actor);
    }

    public function storePackingImage(GrossSalesInvoice $invoice, UploadedFile $file, User $actor): GrossSalesInvoice
    {
        return $this->fulfillment->storePackingImage($invoice, $file, $actor);
    }

    public function markReadyInWarehouse(GrossSalesInvoice $invoice): GrossSalesInvoice
    {
        return $this->fulfillment->markReadyInWarehouse($invoice);
    }

    /**
     * Hand invoice to courier: stock ON_HAND → WITH_COURIER + allocations + COD flags.
     */
    public function handToCourier(GrossSalesInvoice $invoice, ?User $actor = null): GrossSalesInvoice
    {
        return $this->fulfillment->handToCourier($invoice, $actor);
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

    /**
     * Admin hard-delete — any status. Restores stock, removes COD voucher + media files.
     */
    public function delete(GrossSalesInvoice $invoice, ?User $actor = null): void
    {
        DB::transaction(function () use ($invoice, $actor) {
            /** @var GrossSalesInvoice $locked */
            $locked = GrossSalesInvoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            $this->unwindFulfillmentStock($locked, $actor);
            $this->deleteCourierCodVoucher($locked);
            $this->deleteInvoiceFiles($locked);
            $locked->delete();
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

            if ($locked->fulfillment_status !== GrossSalesInvoice::FULFILLMENT_WITH_COURIER) {
                throw GrossException::notWithCourier();
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

                $targetReturned = (int) $allocLocked->qty_shipped_pieces
                    - (int) $allocLocked->qty_delivered_pieces
                    - (int) $allocLocked->qty_lost_pieces;

                $deltaReturned = $targetReturned - (int) $allocLocked->qty_returned_pieces;
                if ($deltaReturned > 0) {
                    $item = $line->item;
                    if ($item === null) {
                        throw GrossException::invalidTransition();
                    }

                    $this->stock->recordClientReturn(
                        $item,
                        $deltaReturned,
                        (int) $allocLocked->conversion_factor_snapshot,
                        GrossShipmentAllocation::class,
                        (int) $allocLocked->id,
                        $actor,
                    );
                }

                $allocLocked->qty_returned_pieces = $targetReturned;
                $allocLocked->save();
                $allocLocked->assertInvariant();
            }

            $feeStatus = (float) $locked->shipping_fee > 0
                ? GrossSalesInvoice::SHIPPING_FEE_PENDING
                : GrossSalesInvoice::SHIPPING_FEE_NONE;

            $locked->update([
                'status' => GrossSalesInvoice::STATUS_REFUSED,
                'shipping_fee_status' => $feeStatus,
                'cod_collection_status' => GrossSalesInvoice::COD_LOST,
                'refused_at' => now(),
                'refused_by' => $actor->id,
                'refuse_reason' => $reason,
            ]);

            $this->net->recalculateInvoice($locked->fresh(['lines.allocation']));

            return $locked->fresh(['lines.allocation', 'lines.item']);
        });
    }

    /**
     * Receive returned/refused pieces from courier into warehouse stock.
     * Independent of COD money collection — either step may happen first.
     */
    public function receiveReturnedPiecesFromCourier(GrossSalesInvoice $invoice, User $actor): GrossSalesInvoice
    {
        return DB::transaction(function () use ($invoice, $actor) {
            /** @var GrossSalesInvoice $locked */
            $locked = GrossSalesInvoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [
                GrossSalesInvoice::STATUS_CONFIRMED,
                GrossSalesInvoice::STATUS_DELIVERED,
                GrossSalesInvoice::STATUS_REFUSED,
            ], true)) {
                throw GrossException::invalidTransition();
            }

            $locked->load(['lines.allocation']);

            if ($locked->returnedPiecesPendingHandover() < 1) {
                return $locked->fresh(['lines.allocation', 'lines.item']);
            }

            foreach ($locked->lines as $line) {
                $allocation = $line->allocation;
                if ($allocation !== null && $allocation->returnedPendingHandover() > 0) {
                    $this->allocations->receiveReturnedFromCourier($allocation, $actor);
                }
            }

            return $locked->fresh(['lines.allocation', 'lines.item']);
        });
    }

    /** @deprecated Use receiveReturnedPiecesFromCourier() */
    public function receiveRefusedFromCourier(GrossSalesInvoice $invoice, User $actor): GrossSalesInvoice
    {
        return $this->receiveReturnedPiecesFromCourier($invoice, $actor);
    }

    /**
     * Collect merchandise COD from courier (money only).
     * Does not move returned pieces into warehouse — use receiveReturnedPiecesFromCourier().
     *
     * @param  array{post_to_treasury?: bool, treasury_account_id?: int|null}  $options
     */
    public function collectCod(GrossSalesInvoice $invoice, User $actor, array $options = []): GrossSalesInvoice
    {
        return $this->cod->collectCod($invoice, $actor, $options);
    }

    /** @deprecated Use collectCod() — kept for tests migrating from fee-only collection. */
    public function collectShippingFee(GrossSalesInvoice $invoice, User $actor): GrossSalesInvoice
    {
        return $this->cod->collectShippingFee($invoice, $actor);
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

            if ($locked->fulfillment_status !== GrossSalesInvoice::FULFILLMENT_WITH_COURIER) {
                throw GrossException::notWithCourier();
            }

            if (! $locked->canMarkDelivered()) {
                throw GrossException::codStillPending();
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

        $this->net->recalculateInvoice($invoice->fresh(['lines.allocation']));

        return $invoice->refresh();
    }

    /**
     * @return array<string, mixed>
     */
    private function resetFulfillmentAttributes(string $priorStatus): array
    {
        $base = [
            'shipping_fee_status' => GrossSalesInvoice::SHIPPING_FEE_NONE,
            'cod_collection_status' => GrossSalesInvoice::COD_NONE,
            'cod_collected_amount' => null,
            'cod_collected_at' => null,
            'cod_collected_by' => null,
            'shipping_fee_collected_at' => null,
            'shipping_fee_collected_by' => null,
            'refused_at' => null,
            'refused_by' => null,
            'refuse_reason' => null,
            'delivered_at' => null,
            'packing_image_path' => null,
            'packing_image_original_name' => null,
            'packing_image_mime' => null,
            'packing_image_size_bytes' => null,
            'packing_uploaded_at' => null,
            'packing_uploaded_by' => null,
        ];

        if ($priorStatus === GrossSalesInvoice::STATUS_DRAFT
            || $priorStatus === GrossSalesInvoice::STATUS_CANCELLED) {
            return $base;
        }

        return array_merge($base, [
            'status' => GrossSalesInvoice::STATUS_CONFIRMED,
            'fulfillment_status' => GrossSalesInvoice::FULFILLMENT_PREPARING,
            'cancelled_at' => null,
            'confirmed_at' => now(),
        ]);
    }

    private function unwindFulfillmentStock(GrossSalesInvoice $invoice, ?User $actor): void
    {
        $invoice->load(['lines.allocation', 'lines.item']);

        foreach ($invoice->lines as $line) {
            $alloc = $line->allocation;
            if ($alloc === null) {
                continue;
            }

            $this->restoreAllocationStock($alloc, $line->item, $actor);
            $alloc->delete();
        }
    }

    private function restoreAllocationStock(
        GrossShipmentAllocation $alloc,
        ?GrossItem $item,
        ?User $actor,
    ): void {
        if ($item === null) {
            return;
        }

        $factor = (int) $alloc->conversion_factor_snapshot;
        $refType = GrossShipmentAllocation::class;
        $refId = (int) $alloc->id;

        $delivered = (int) $alloc->qty_delivered_pieces;
        if ($delivered > 0) {
            $this->stock->reverseDelivery($item, $delivered, $factor, $refType, $refId, $actor);
        }

        $lost = (int) $alloc->qty_lost_pieces;
        if ($lost > 0) {
            $this->stock->reverseLoss($item, $lost, $factor, $refType, $refId, $actor);
        }

        $withCourier = (int) $alloc->qty_with_courier_pieces;
        if ($withCourier > 0) {
            $this->stock->applyReturn(
                $item,
                $withCourier,
                $factor,
                $refType,
                $refId,
                $actor,
                GrossStockMovement::TYPE_CANCEL_RESTORE,
            );
        }
    }

    private function clearPackingImage(GrossSalesInvoice $invoice): void
    {
        if ($invoice->packing_image_path && Storage::disk('local')->exists($invoice->packing_image_path)) {
            Storage::disk('local')->delete($invoice->packing_image_path);
        }
    }

    private function deleteInvoiceFiles(GrossSalesInvoice $invoice): void
    {
        $this->clearPackingImage($invoice);
        $invoice->loadMissing('media');

        foreach ($invoice->media as $media) {
            if ($media->file_path && Storage::disk('local')->exists($media->file_path)) {
                Storage::disk('local')->delete($media->file_path);
            }
        }
    }

    private function deleteCourierCodVoucher(GrossSalesInvoice $invoice): void
    {
        $invoice->loadMissing('courierCodTreasuryVoucher.attachments');
        $voucher = $invoice->courierCodTreasuryVoucher;
        if ($voucher === null) {
            return;
        }

        foreach ($voucher->attachments as $attachment) {
            if ($attachment->file_path && Storage::disk('local')->exists($attachment->file_path)) {
                Storage::disk('local')->delete($attachment->file_path);
            }
        }

        $voucher->attachments()->delete();
        $voucher->delete();
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
                'returned_amount' => 0,
                'lost_amount' => 0,
                'net_line_total' => $lineTotal,
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

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveCustomer(array $data): ?GrossCustomer
    {
        $name = trim((string) ($data['customer_name'] ?? ''));
        $phone = trim((string) ($data['customer_phone'] ?? ''));
        $address = $data['customer_address'] ?? null;

        if ($name !== '' && $phone !== '') {
            return $this->customers->findOrCreateFromInvoice(
                $name,
                $phone,
                is_string($address) ? $address : null,
            );
        }

        if (! empty($data['customer_id'])) {
            return GrossCustomer::query()->find($data['customer_id']);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveShipping(array $data, ?GrossShippingCompany $fallback = null): ?GrossShippingCompany
    {
        if (! empty($data['shipping_company_id'])) {
            return GrossShippingCompany::query()->find($data['shipping_company_id']);
        }

        $name = trim((string) ($data['shipping_company_name'] ?? ''));
        if ($name !== '') {
            return $this->shippingCompanies->findOrCreateByName($name);
        }

        if (array_key_exists('shipping_company_id', $data) || array_key_exists('shipping_company_name', $data)) {
            return null;
        }

        return $fallback;
    }
}
