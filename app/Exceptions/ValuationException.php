<?php

namespace App\Exceptions;

use RuntimeException;

class ValuationException extends RuntimeException
{
    public static function notAllowed(): self
    {
        return new self('valuation.errors.not_allowed');
    }

    public static function notAssigned(): self
    {
        return new self('valuation.errors.not_assigned');
    }
}
