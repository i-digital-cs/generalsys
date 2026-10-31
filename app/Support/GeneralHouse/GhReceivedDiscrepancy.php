<?php

namespace App\Support\GeneralHouse;

final class GhReceivedDiscrepancy
{
    public const MATCH = 'MATCH';

    public const SHORT = 'SHORT';

    public const OVER = 'OVER';

    public const ADDITIONAL = 'ADDITIONAL';

    public const MISSING = 'MISSING';

    /** @var list<string> */
    public const ALL = [self::MATCH, self::SHORT, self::OVER, self::ADDITIONAL, self::MISSING];
}
