<?php

namespace Tests\Unit\Gross;

use App\Exceptions\GrossException;
use App\Models\Gross\GrossStockMovement;
use App\Services\Gross\UnitConversion;
use PHPUnit\Framework\TestCase;

class UnitConversionTest extends TestCase
{
    public function test_piece_factor_is_one(): void
    {
        $this->assertSame(1, UnitConversion::factorFor(GrossStockMovement::UNIT_PIECE, 12));
        $this->assertSame(3, UnitConversion::qtyPieces(3, GrossStockMovement::UNIT_PIECE, 1));
    }

    public function test_carton_uses_pieces_per_carton(): void
    {
        $factor = UnitConversion::factorFor(GrossStockMovement::UNIT_CARTON, 24);
        $this->assertSame(24, $factor);
        $this->assertSame(48, UnitConversion::qtyPieces(2, GrossStockMovement::UNIT_CARTON, $factor));
    }

    public function test_rejects_invalid_unit_and_qty(): void
    {
        $this->expectException(GrossException::class);
        UnitConversion::factorFor('BAG', 10);
    }

    public function test_rejects_zero_qty(): void
    {
        $this->expectException(GrossException::class);
        UnitConversion::qtyPieces(0, GrossStockMovement::UNIT_PIECE, 1);
    }

    public function test_piece_unit_requires_factor_one(): void
    {
        $this->expectException(GrossException::class);
        UnitConversion::qtyPieces(2, GrossStockMovement::UNIT_PIECE, 12);
    }
}
