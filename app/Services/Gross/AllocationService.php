<?php

namespace App\Services\Gross;

use App\Exceptions\GrossException;
use App\Models\Gross\GrossSalesInvoice;
use App\Models\Gross\GrossShipmentAllocation;
use App\Models\Gross\GrossStockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AllocationService
{
    public function __construct(
        private readonly StockService $stock,
        private readonly SalesInvoiceNetService $net,
    ) {}

    /**
     * Client refused merchandise — money adjusts now; pieces stay with courier until handover.
     */
    public function partialReturn(
        GrossShipmentAllocation $allocation,
        int $qtyPieces,
        ?User $actor = null,
    ): GrossShipmentAllocation {
        if ($qtyPieces < 1) {
            throw GrossException::invalidQty();
        }

        return DB::transaction(function () use ($allocation, $qtyPieces, $actor) {
            /** @var GrossShipmentAllocation $locked */
            $locked = GrossShipmentAllocation::query()
                ->whereKey($allocation->id)
                ->lockForUpdate()
                ->firstOrFail();

            $locked->load(['line.item', 'line.invoice']);

            $invoice = $locked->line?->invoice;
            if ($invoice === null || $invoice->status !== GrossSalesInvoice::STATUS_CONFIRMED) {
                throw GrossException::invalidTransition();
            }

            $maxReturnable = $locked->deliverableWithCourier();
            if ($qtyPieces > $maxReturnable) {
                throw GrossException::allocationQty();
            }

            $locked->qty_returned_pieces += $qtyPieces;
            $locked->save();
            $locked->assertInvariant();

            $item = $locked->line?->item;
            if ($item !== null) {
                $this->stock->recordClientReturn(
                    $item,
                    $qtyPieces,
                    (int) $locked->conversion_factor_snapshot,
                    GrossShipmentAllocation::class,
                    (int) $locked->id,
                    $actor,
                );
            }

            $this->net->recalculateInvoice($invoice->fresh(['lines.allocation']));

            return $locked->fresh(['line.item']);
        });
    }

    /**
     * Courier hands refused/returned pieces back to warehouse (separate from COD collect).
     * Optional qty for partial handover; null = receive all pending for this allocation.
     */
    public function receiveReturnedFromCourier(
        GrossShipmentAllocation $allocation,
        ?User $actor = null,
        ?int $qtyPieces = null,
    ): GrossShipmentAllocation {
        return DB::transaction(function () use ($allocation, $actor, $qtyPieces) {
            /** @var GrossShipmentAllocation $locked */
            $locked = GrossShipmentAllocation::query()
                ->whereKey($allocation->id)
                ->lockForUpdate()
                ->firstOrFail();

            $locked->load(['line.item', 'line.invoice']);

            $invoice = $locked->line?->invoice;
            if ($invoice === null || ! in_array($invoice->status, [
                GrossSalesInvoice::STATUS_CONFIRMED,
                GrossSalesInvoice::STATUS_DELIVERED,
                GrossSalesInvoice::STATUS_REFUSED,
            ], true)) {
                throw GrossException::invalidTransition();
            }

            $pending = $locked->returnedPendingHandover();
            if ($pending < 1) {
                return $locked;
            }

            $receiveQty = $qtyPieces ?? $pending;
            if ($receiveQty < 1 || $receiveQty > $pending) {
                throw GrossException::allocationQty();
            }

            $item = $locked->line?->item;
            if ($item === null) {
                throw GrossException::invalidTransition();
            }

            if ((int) $locked->qty_with_courier_pieces < $receiveQty) {
                throw GrossException::allocationQty();
            }

            $returnType = $invoice->status === GrossSalesInvoice::STATUS_REFUSED
                ? GrossStockMovement::TYPE_REFUSE_RETURN
                : GrossStockMovement::TYPE_SALE_RETURN;

            $this->stock->applyReturn(
                $item,
                $receiveQty,
                (int) $locked->conversion_factor_snapshot,
                GrossShipmentAllocation::class,
                (int) $locked->id,
                $actor,
                $returnType,
            );

            $locked->qty_with_courier_pieces -= $receiveQty;
            $locked->qty_returned_received_pieces += $receiveQty;
            $locked->save();
            $locked->assertInvariant();

            return $locked->fresh(['line.item']);
        });
    }

    public function confirmLoss(
        GrossShipmentAllocation $allocation,
        int $qtyPieces,
        ?User $actor = null,
    ): GrossShipmentAllocation {
        if ($qtyPieces < 1) {
            throw GrossException::invalidQty();
        }

        return DB::transaction(function () use ($allocation, $qtyPieces, $actor) {
            /** @var GrossShipmentAllocation $locked */
            $locked = GrossShipmentAllocation::query()
                ->whereKey($allocation->id)
                ->lockForUpdate()
                ->firstOrFail();

            $locked->load(['line.item', 'line.invoice']);

            $invoice = $locked->line?->invoice;
            if ($invoice === null || $invoice->status !== GrossSalesInvoice::STATUS_CONFIRMED) {
                throw GrossException::invalidTransition();
            }

            if ($qtyPieces > $locked->deliverableWithCourier()) {
                throw GrossException::allocationQty();
            }

            $item = $locked->line->item;
            if ($item === null) {
                throw GrossException::invalidTransition();
            }

            $this->stock->applyLoss(
                $item,
                $qtyPieces,
                (int) $locked->conversion_factor_snapshot,
                GrossShipmentAllocation::class,
                (int) $locked->id,
                $actor,
            );

            $locked->qty_lost_pieces += $qtyPieces;
            $locked->qty_with_courier_pieces -= $qtyPieces;
            $locked->save();
            $locked->assertInvariant();

            $this->net->recalculateInvoice($invoice->fresh(['lines.allocation']));

            return $locked->fresh(['line.item']);
        });
    }

    /**
     * Mark deliverable with-courier qty as delivered (excludes client-refused pieces still with courier).
     */
    public function confirmDelivered(
        GrossShipmentAllocation $allocation,
        ?User $actor = null,
    ): GrossShipmentAllocation {
        return DB::transaction(function () use ($allocation, $actor) {
            /** @var GrossShipmentAllocation $locked */
            $locked = GrossShipmentAllocation::query()
                ->whereKey($allocation->id)
                ->lockForUpdate()
                ->firstOrFail();

            $locked->load(['line.item', 'line.invoice']);

            $invoice = $locked->line?->invoice;
            if ($invoice === null || ! in_array($invoice->status, [
                GrossSalesInvoice::STATUS_CONFIRMED,
                GrossSalesInvoice::STATUS_DELIVERED,
            ], true)) {
                throw GrossException::invalidTransition();
            }

            $qtyPieces = $locked->deliverableWithCourier();
            if ($qtyPieces < 1) {
                return $locked;
            }

            $item = $locked->line->item;
            if ($item === null) {
                throw GrossException::invalidTransition();
            }

            $this->stock->applyDelivery(
                $item,
                $qtyPieces,
                (int) $locked->conversion_factor_snapshot,
                GrossShipmentAllocation::class,
                (int) $locked->id,
                $actor,
            );

            $locked->qty_delivered_pieces += $qtyPieces;
            $locked->qty_with_courier_pieces -= $qtyPieces;
            $locked->save();
            $locked->assertInvariant();

            return $locked->fresh(['line.item']);
        });
    }
}
