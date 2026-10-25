<?php

namespace App\Support\GeneralHouse;

final class GhCatalogStatus
{
    public const ACTIVE = 'ACTIVE';

    public const INACTIVE = 'INACTIVE';

    /** @var list<string> */
    public const ALL = [self::ACTIVE, self::INACTIVE];
}
