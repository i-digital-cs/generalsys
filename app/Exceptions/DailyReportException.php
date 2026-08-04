<?php

namespace App\Exceptions;

use RuntimeException;

/** Daily report rule violations (mapped to 422). */
class DailyReportException extends RuntimeException
{
    public static function clockOutRequired(): self
    {
        return new self('report.errors.clock_out_required');
    }

    public static function lockedByTlResponse(): self
    {
        return new self('report.errors.locked');
    }

    public static function noAttendance(): self
    {
        return new self('report.errors.no_attendance');
    }

    public static function reportMissing(): self
    {
        return new self('report.errors.report_missing');
    }

    public static function notTeamMember(): self
    {
        return new self('report.errors.not_team_member');
    }
}
