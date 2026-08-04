<?php

namespace App\Exceptions;

use RuntimeException;

/** Vacation request rule violations (mapped to 422). */
class VacationRequestException extends RuntimeException
{
    public static function invalidDateRange(): self
    {
        return new self('vacations.errors.invalid_date_range');
    }

    public static function invalidDays(): self
    {
        return new self('vacations.errors.invalid_days');
    }

    public static function overlapsExisting(): self
    {
        return new self('vacations.errors.overlap');
    }

    public static function notPending(): self
    {
        return new self('vacations.errors.not_pending');
    }

    public static function notTeamMember(): self
    {
        return new self('vacations.errors.not_team_member');
    }

    public static function mustBeSingleDay(): self
    {
        return new self('vacations.errors.must_be_single_day');
    }

    public static function invalidRestDay(): self
    {
        return new self('vacations.errors.invalid_rest_day');
    }

    public static function restDayReserved(): self
    {
        return new self('vacations.errors.rest_day_reserved');
    }

    public static function cannotReplaceSameDay(): self
    {
        return new self('vacations.errors.cannot_replace_same_day');
    }

    public static function notRejected(): self
    {
        return new self('vacations.errors.not_rejected');
    }

    public static function cannotForce(): self
    {
        return new self('vacations.errors.cannot_force');
    }
}
