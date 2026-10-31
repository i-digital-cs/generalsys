<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Parse calendar months (Y-m) without day-of-month overflow.
 *
 * Carbon::createFromFormat('Y-m', '2026-09') on day 31 becomes October —
 * always anchor on day 1.
 */
final class YearMonth
{
    public static function start(string $ym): Carbon
    {
        return Carbon::createFromFormat('Y-m-d', $ym.'-01')->startOfMonth();
    }
}
