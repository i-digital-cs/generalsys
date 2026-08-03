<?php

namespace App\Exceptions;

use RuntimeException;

/** Domain rule violation for TL shift assignments (mapped to 422). */
class ShiftAssignmentException extends RuntimeException
{
    public static function duplicateScheduled(): self
    {
        return new self('shifts.errors.duplicate');
    }

    public static function notCancellable(): self
    {
        return new self('shifts.errors.not_cancellable');
    }

    public static function notEditable(): self
    {
        return new self('shifts.errors.not_editable');
    }

    public static function employeeNotAssignable(): self
    {
        return new self('shifts.errors.employee_not_assignable');
    }
}
