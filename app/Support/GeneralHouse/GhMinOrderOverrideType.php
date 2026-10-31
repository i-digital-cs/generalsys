<?php

namespace App\Support\GeneralHouse;

final class GhMinOrderOverrideType
{
    public const APPLY_DEFAULT = 'APPLY_DEFAULT';

    public const OVERRIDE_AMOUNT = 'OVERRIDE_AMOUNT';

    public const WAIVE = 'WAIVE';

    /** @var list<string> */
    public const ALL = [self::APPLY_DEFAULT, self::OVERRIDE_AMOUNT, self::WAIVE];
}
