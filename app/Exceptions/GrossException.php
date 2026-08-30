<?php

namespace App\Exceptions;

use RuntimeException;

class GrossException extends RuntimeException
{
    public static function message(string $key): self
    {
        return new self($key);
    }

    public static function insufficientStock(): self
    {
        return new self('gross.errors.insufficient_stock');
    }

    public static function negativeStock(): self
    {
        return new self('gross.errors.negative_stock');
    }

    public static function invalidUnit(): self
    {
        return new self('gross.errors.invalid_unit');
    }

    public static function invalidQty(): self
    {
        return new self('gross.errors.invalid_qty');
    }

    public static function piecesPerCartonLocked(): self
    {
        return new self('gross.errors.pieces_per_carton_locked');
    }

    public static function invalidTransition(): self
    {
        return new self('gross.errors.invalid_transition');
    }

    public static function cannotCancel(): self
    {
        return new self('gross.errors.cannot_cancel');
    }

    public static function allocationQty(): self
    {
        return new self('gross.errors.allocation_qty');
    }

    public static function notDraft(): self
    {
        return new self('gross.errors.not_draft');
    }
}
