<?php

namespace App\Services\Payroll;

use RuntimeException;

/** Thrown when classified attendance days ≠ calendar days in the month (Gate 4.10). */
class MonthCoverageException extends RuntimeException
{
    public function __construct(
        public readonly int $userId,
        public readonly int $month,
        public readonly int $year,
        public readonly int $classifiedDays,
        public readonly int $expectedDays,
    ) {
        parent::__construct(sprintf(
            'Payroll month coverage failed for user %d (%02d/%d): classified %d of %d days.',
            $userId,
            $month,
            $year,
            $classifiedDays,
            $expectedDays,
        ));
    }
}
