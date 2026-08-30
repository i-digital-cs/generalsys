<?php

namespace App\Services\Gross;

use App\Exceptions\GrossException;
use App\Models\Gross\GrossStockMovement;

/**
 * Piece/carton conversion — base stock unit is always PIECE.
 */
final class UnitConversion
{
    public static function factorFor(string $unit, int $piecesPerCarton): int
    {
        if ($piecesPerCarton < 1) {
            throw GrossException::invalidQty();
        }

        return match ($unit) {
            GrossStockMovement::UNIT_PIECE => 1,
            GrossStockMovement::UNIT_CARTON => $piecesPerCarton,
            default => throw GrossException::invalidUnit(),
        };
    }

    public static function qtyPieces(int $qty, string $unit, int $factor): int
    {
        if ($qty < 1) {
            throw GrossException::invalidQty();
        }

        if ($factor < 1) {
            throw GrossException::invalidQty();
        }

        if (! in_array($unit, GrossStockMovement::UNITS, true)) {
            throw GrossException::invalidUnit();
        }

        if ($unit === GrossStockMovement::UNIT_PIECE && $factor !== 1) {
            throw GrossException::invalidUnit();
        }

        return $qty * $factor;
    }
}
