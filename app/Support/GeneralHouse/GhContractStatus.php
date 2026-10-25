<?php

namespace App\Support\GeneralHouse;

final class GhContractStatus
{
    public const DRAFT = 'DRAFT';

    public const ACTIVE = 'ACTIVE';

    public const EXPIRED = 'EXPIRED';

    public const CANCELLED = 'CANCELLED';

    /** @var list<string> */
    public const ALL = [self::DRAFT, self::ACTIVE, self::EXPIRED, self::CANCELLED];
}
