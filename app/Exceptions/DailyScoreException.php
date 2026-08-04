<?php

namespace App\Exceptions;

use RuntimeException;

/** Daily evaluation score rule violations (mapped to 422). */
class DailyScoreException extends RuntimeException
{
    public static function outOfRange(): self
    {
        return new self('evaluation.errors.score_out_of_range');
    }

    public static function notTeamMember(): self
    {
        return new self('evaluation.errors.not_team_member');
    }
}
