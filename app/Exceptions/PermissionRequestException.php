<?php

namespace App\Exceptions;

use RuntimeException;

/** Permission request rule violations (mapped to 422). */
class PermissionRequestException extends RuntimeException
{
    /**
     * @param  array<string, string|int|float>  $replace
     */
    public function __construct(
        string $message,
        private readonly array $replace = [],
    ) {
        parent::__construct($message);
    }

    /**
     * @return array<string, string|int|float>
     */
    public function replace(): array
    {
        return $this->replace;
    }

    public static function invalidTimeRange(): self
    {
        return new self('permissions.errors.invalid_time_range');
    }

    public static function noTeamLeader(): self
    {
        return new self('permissions.errors.no_team_leader');
    }

    public static function notPending(): self
    {
        return new self('permissions.errors.not_pending');
    }

    public static function notTeamMember(): self
    {
        return new self('permissions.errors.not_team_member');
    }

    public static function notRejected(): self
    {
        return new self('permissions.errors.not_rejected');
    }

    public static function cannotForce(): self
    {
        return new self('permissions.errors.cannot_force');
    }

    public static function invalidAbsence(): self
    {
        return new self('permissions.errors.invalid_absence');
    }

    public static function dayAlreadyWorked(string $date): self
    {
        return new self('permissions.errors.day_already_worked', ['date' => $date]);
    }

    public static function duplicateAbsence(): self
    {
        return new self('permissions.errors.duplicate_absence');
    }

    public static function invalidAbsencePeriod(): self
    {
        return new self('permissions.errors.invalid_absence_period');
    }

    public static function absencePeriodTooLong(): self
    {
        return new self('permissions.errors.absence_period_too_long');
    }

    public static function absenceNoWorkingDays(): self
    {
        return new self('permissions.errors.absence_no_working_days');
    }
}
