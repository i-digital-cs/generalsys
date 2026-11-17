<?php

namespace App\Support\GeneralHouse;

final class GhDebitAdjustmentStatus
{
    public const DRAFT = 'DRAFT';

    public const ISSUED = 'ISSUED';

    public const CANCELLED = 'CANCELLED';

    /** @var list<string> */
    public const ALL = [
        self::DRAFT,
        self::ISSUED,
        self::CANCELLED,
    ];
}
