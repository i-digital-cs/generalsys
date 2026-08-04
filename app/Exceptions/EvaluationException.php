<?php

namespace App\Exceptions;

use RuntimeException;

/** Monthly evaluation rule violations. */
class EvaluationException extends RuntimeException
{
    public static function notTeamMember(): self
    {
        return new self('evaluation.errors.not_team_member');
    }

    public static function invalidMonth(): self
    {
        return new self('evaluation.errors.invalid_month');
    }
}
