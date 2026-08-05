<?php

namespace App\Exceptions;

use RuntimeException;

/** Domain rule violation for attendance clock flows (mapped to 422). */
class AttendanceException extends RuntimeException
{
    public static function openAttendanceExists(): self
    {
        return new self('clock.errors.open_exists');
    }

    public static function noOpenAttendance(): self
    {
        return new self('clock.errors.no_open');
    }

    public static function shiftAssignmentRequired(string $shiftType): self
    {
        return new self('clock.errors.assignment_required');
    }

    public static function invalidShiftType(string $shiftType): self
    {
        return new self('clock.errors.invalid_shift');
    }

    public static function shiftAlreadyRecorded(): self
    {
        return new self('clock.errors.shift_already_recorded');
    }

    public static function planMismatch(): self
    {
        return new self('clock.errors.plan_mismatch');
    }

    public static function dailyReportRequired(): self
    {
        return new self('clock.errors.report_required');
    }
}
