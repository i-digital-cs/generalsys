<?php

namespace App\Support\GeneralHouse;

final class GhDiscountType
{
    public const NONE = 'NONE';

    public const FIXED = 'FIXED';

    public const PERCENT = 'PERCENT';

    /** @var list<string> */
    public const ALL = [self::NONE, self::FIXED, self::PERCENT];
}
