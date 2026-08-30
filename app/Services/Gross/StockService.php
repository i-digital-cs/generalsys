<?php

namespace App\Services\Gross;

use App\Exceptions\GrossException;
use App\Models\Gross\GrossItem;
use App\Models\Gross\GrossStockBalance;
use App\Models\Gross\GrossStockMovement;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Sole writer of gross_stock_balances + gross_stock_movements.
 */
class StockService
{
    /**
     * Read-only ledger of stock movements.
     *
     * @param  array{q?: string, type?: string, item_id?: int|null, from?: string, to?: string}  $filters
     */
    public function paginateMovements(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $q = GrossStockMovement::query()
            ->with(['item', 'creator'])
            ->orderByDesc('id');

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $like = '%'.$search.'%';
            $q->where(function ($inner) use ($like): void {
                $inner->where('notes', 'like', $like)
                    ->orWhereHas('item', function ($item) use ($like): void {
                        $item->where('code', 'like', $like)
                            ->orWhere('name', 'like', $like);
                    });
            });
        }

        if (! empty($filters['type'])) {
            $q->where('type', $filters['type']);
        }

        if (! empty($filters['item_id'])) {
            $q->where('item_id', (int) $filters['item_id']);
        }

        if (! empty($filters['from'])) {
            $q->whereDate('created_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $q->whereDate('created_at', '<=', $filters['to']);
        }

        return $q->paginate(min($perPage, 50))->withQueryString();
    }

    public function ensureBalanceRow(GrossItem $item): GrossStockBalance
    {
        return GrossStockBalance::query()->firstOrCreate(
            ['item_id' => $item->id],
            [
                'qty_on_hand_pieces' => 0,
                'qty_with_courier_pieces' => 0,
                'qty_lost_pieces' => 0,
            ],
        );
    }

    public function adjustIn(
        GrossItem $item,
        int $qty,
        string $unit,
        ?User $actor = null,
        ?string $notes = null,
    ): GrossStockMovement {
        $factor = UnitConversion::factorFor($unit, (int) $item->pieces_per_carton);
        $pieces = UnitConversion::qtyPieces($qty, $unit, $factor);

        return $this->writeOnHandDelta(
            $item,
            GrossStockMovement::TYPE_ADJUSTMENT_IN,
            $qty,
            $unit,
            $factor,
            $pieces,
            +$pieces,
            $actor,
            $notes,
        );
    }

    public function adjustOut(
        GrossItem $item,
        int $qty,
        string $unit,
        ?User $actor = null,
        ?string $notes = null,
    ): GrossStockMovement {
        $factor = UnitConversion::factorFor($unit, (int) $item->pieces_per_carton);
        $pieces = UnitConversion::qtyPieces($qty, $unit, $factor);

        return $this->writeOnHandDelta(
            $item,
            GrossStockMovement::TYPE_ADJUSTMENT_OUT,
            $qty,
            $unit,
            $factor,
            $pieces,
            -$pieces,
            $actor,
            $notes,
        );
    }

    /**
     * Confirm purchase: ON_HAND + pieces.
     */
    public function applyPurchaseIn(
        GrossItem $item,
        int $qtyPieces,
        int $conversionFactor,
        string $unit,
        int $qty,
        string $refType,
        int $refId,
        ?User $actor = null,
    ): GrossStockMovement {
        if ($qtyPieces < 1) {
            throw GrossException::invalidQty();
        }

        return DB::transaction(function () use ($item, $qtyPieces, $conversionFactor, $unit, $qty, $refType, $refId, $actor) {
            $balance = $this->lockBalance($item);
            $balance->qty_on_hand_pieces += $qtyPieces;
            $balance->save();

            return $this->createMovement(
                $item,
                GrossStockMovement::TYPE_PURCHASE_IN,
                $qty,
                $unit,
                $conversionFactor,
                $qtyPieces,
                $refType,
                $refId,
                $actor,
            );
        });
    }

    /**
     * Confirm sale: ON_HAND − pieces; WITH_COURIER + pieces.
     */
    public function applySaleOut(
        GrossItem $item,
        int $qtyPieces,
        int $conversionFactor,
        string $unit,
        int $qty,
        string $refType,
        int $refId,
        ?User $actor = null,
    ): GrossStockMovement {
        if ($qtyPieces < 1) {
            throw GrossException::invalidQty();
        }

        return DB::transaction(function () use ($item, $qtyPieces, $conversionFactor, $unit, $qty, $refType, $refId, $actor) {
            $balance = $this->lockBalance($item);

            if ($balance->qty_on_hand_pieces < $qtyPieces) {
                throw GrossException::insufficientStock();
            }

            $balance->qty_on_hand_pieces -= $qtyPieces;
            $balance->qty_with_courier_pieces += $qtyPieces;
            $balance->save();

            return $this->createMovement(
                $item,
                GrossStockMovement::TYPE_SALE_OUT,
                $qty,
                $unit,
                $conversionFactor,
                $qtyPieces,
                $refType,
                $refId,
                $actor,
            );
        });
    }

    public function applyReturn(
        GrossItem $item,
        int $qtyPieces,
        int $conversionFactor,
        string $refType,
        int $refId,
        ?User $actor = null,
        string $type = GrossStockMovement::TYPE_SALE_RETURN,
    ): GrossStockMovement {
        if ($qtyPieces < 1) {
            throw GrossException::invalidQty();
        }

        return DB::transaction(function () use ($item, $qtyPieces, $conversionFactor, $refType, $refId, $actor, $type) {
            $balance = $this->lockBalance($item);

            if ($balance->qty_with_courier_pieces < $qtyPieces) {
                throw GrossException::insufficientStock();
            }

            $balance->qty_with_courier_pieces -= $qtyPieces;
            $balance->qty_on_hand_pieces += $qtyPieces;
            $balance->save();

            return $this->createMovement(
                $item,
                $type,
                $qtyPieces,
                GrossStockMovement::UNIT_PIECE,
                $conversionFactor,
                $qtyPieces,
                $refType,
                $refId,
                $actor,
            );
        });
    }

    public function applyRefuseReturn(
        GrossItem $item,
        int $qtyPieces,
        int $conversionFactor,
        string $refType,
        int $refId,
        ?User $actor = null,
    ): GrossStockMovement {
        return $this->applyReturn(
            $item,
            $qtyPieces,
            $conversionFactor,
            $refType,
            $refId,
            $actor,
            GrossStockMovement::TYPE_REFUSE_RETURN,
        );
    }

    /**
     * Loss: WITH_COURIER − pieces; LOST + pieces. Never restores ON_HAND.
     */
    public function applyLoss(
        GrossItem $item,
        int $qtyPieces,
        int $conversionFactor,
        string $refType,
        int $refId,
        ?User $actor = null,
    ): GrossStockMovement {
        if ($qtyPieces < 1) {
            throw GrossException::invalidQty();
        }

        return DB::transaction(function () use ($item, $qtyPieces, $conversionFactor, $refType, $refId, $actor) {
            $balance = $this->lockBalance($item);

            if ($balance->qty_with_courier_pieces < $qtyPieces) {
                throw GrossException::insufficientStock();
            }

            $balance->qty_with_courier_pieces -= $qtyPieces;
            $balance->qty_lost_pieces += $qtyPieces;
            $balance->save();

            return $this->createMovement(
                $item,
                GrossStockMovement::TYPE_LOSS_CONFIRM,
                $qtyPieces,
                GrossStockMovement::UNIT_PIECE,
                $conversionFactor,
                $qtyPieces,
                $refType,
                $refId,
                $actor,
            );
        });
    }

    private function writeOnHandDelta(
        GrossItem $item,
        string $type,
        int $qty,
        string $unit,
        int $factor,
        int $pieces,
        int $onHandDelta,
        ?User $actor,
        ?string $notes,
    ): GrossStockMovement {
        return DB::transaction(function () use ($item, $type, $qty, $unit, $factor, $pieces, $onHandDelta, $actor, $notes) {
            $balance = $this->lockBalance($item);
            $next = $balance->qty_on_hand_pieces + $onHandDelta;

            if ($next < 0) {
                throw GrossException::negativeStock();
            }

            $balance->qty_on_hand_pieces = $next;
            $balance->save();

            return $this->createMovement(
                $item,
                $type,
                $qty,
                $unit,
                $factor,
                $pieces,
                null,
                null,
                $actor,
                $notes,
            );
        });
    }

    private function lockBalance(GrossItem $item): GrossStockBalance
    {
        $this->ensureBalanceRow($item);

        /** @var GrossStockBalance $balance */
        $balance = GrossStockBalance::query()
            ->where('item_id', $item->id)
            ->lockForUpdate()
            ->firstOrFail();

        return $balance;
    }

    private function createMovement(
        GrossItem $item,
        string $type,
        int $qty,
        string $unit,
        int $factor,
        int $qtyPieces,
        ?string $refType,
        ?int $refId,
        ?User $actor,
        ?string $notes = null,
    ): GrossStockMovement {
        return GrossStockMovement::query()->create([
            'item_id' => $item->id,
            'type' => $type,
            'qty' => $qty,
            'unit' => $unit,
            'conversion_factor' => $factor,
            'qty_pieces' => $qtyPieces,
            'ref_type' => $refType,
            'ref_id' => $refId,
            'notes' => $notes,
            'created_by' => $actor?->id,
        ]);
    }
}
