<?php

namespace App\Services\Gross;

use App\Exceptions\GrossException;
use App\Models\Gross\GrossSalesInvoice;
use App\Models\Gross\GrossShipmentAllocation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AllocationService
{
    public function __construct(private readonly StockService $stock) {}

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

            if ((int) $locked->qty_with_courier_pieces < $qtyPieces) {
                throw GrossException::allocationQty();
            }

            $item = $locked->line->item;
            if ($item === null) {
                throw GrossException::invalidTransition();
            }

            $this->stock->applyReturn(
                $item,
                $qtyPieces,
                (int) $locked->conversion_factor_snapshot,
                GrossShipmentAllocation::class,
                (int) $locked->id,
                $actor,
            );

            $locked->qty_returned_pieces += $qtyPieces;
            $locked->qty_with_courier_pieces -= $qtyPieces;
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

            if ((int) $locked->qty_with_courier_pieces < $qtyPieces) {
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

            return $locked->fresh(['line.item']);
        });
    }

    /**
     * Mark remaining with-courier qty as delivered (customer received).
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

            $qtyPieces = (int) $locked->qty_with_courier_pieces;
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
            $locked->qty_with_courier_pieces = 0;
            $locked->save();
            $locked->assertInvariant();

            return $locked->fresh(['line.item']);
        });
    }
}
