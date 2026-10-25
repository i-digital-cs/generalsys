<?php

namespace App\Support\GeneralHouse;

final class GhMinOrderPolicy
{
    public const VALIDATION_ONLY = 'VALIDATION_ONLY';

    public const SURCHARGE = 'SURCHARGE';

    /** @var list<string> */
    public const ALL = [self::VALIDATION_ONLY, self::SURCHARGE];

    public const DEFAULT = self::SURCHARGE;
}
