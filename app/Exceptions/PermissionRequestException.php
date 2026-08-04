<?php

namespace App\Exceptions;

use RuntimeException;

/** Permission request rule violations (mapped to 422). */
class PermissionRequestException extends RuntimeException
{
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
}
