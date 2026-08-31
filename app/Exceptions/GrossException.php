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

    public static function purchaseReturnQty(): self
    {
        return new self('gross.errors.purchase_return_qty');
    }

    public static function notConfirmed(): self
    {
        return new self('gross.errors.not_confirmed');
    }

    public static function feeNotPending(): self
    {
        return new self('gross.errors.fee_not_pending');
    }

    public static function feeStillPending(): self
    {
        return new self('gross.errors.fee_still_pending');
    }

    public static function insufficientTreasury(): self
    {
        return new self('gross.errors.insufficient_treasury');
    }

    public static function inactiveTreasuryAccount(): self
    {
        return new self('gross.errors.inactive_treasury_account');
    }

    public static function invalidAmount(): self
    {
        return new self('gross.errors.invalid_amount');
    }

    public static function treasurySupplierRequired(): self
    {
        return new self('gross.errors.treasury_supplier_required');
    }

    public static function treasuryExpenseTypeRequired(): self
    {
        return new self('gross.errors.treasury_expense_type_required');
    }

    public static function inactiveExpenseType(): self
    {
        return new self('gross.errors.inactive_expense_type');
    }

    public static function expenseTypeInUse(): self
    {
        return new self('gross.errors.expense_type_in_use');
    }
}
