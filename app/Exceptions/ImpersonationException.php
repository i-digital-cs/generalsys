<?php

namespace App\Exceptions;

use RuntimeException;

/** Domain rule violation for admin impersonation. */
class ImpersonationException extends RuntimeException
{
    public static function disabled(): self
    {
        return new self('impersonation.errors.disabled');
    }

    public static function alreadyImpersonating(): self
    {
        return new self('impersonation.errors.already');
    }

    public static function cannotImpersonateSelf(): self
    {
        return new self('impersonation.errors.self');
    }

    public static function notImpersonating(): self
    {
        return new self('impersonation.errors.not_active');
    }

    public static function impersonatorMissing(): self
    {
        return new self('impersonation.errors.impersonator_missing');
    }
}
