<?php

namespace App\Support\GeneralHouse;

final class GhDriverMovementStatus
{
    public const SCHEDULED = 'SCHEDULED';

    public const READY = 'READY';

    public const COMPLETED = 'COMPLETED';

    public const FAILED = 'FAILED';

    public const CANCELLED = 'CANCELLED';

    /** @var list<string> */
    public const ALL = [
        self::SCHEDULED,
        self::READY,
        self::COMPLETED,
        self::FAILED,
        self::CANCELLED,
    ];
}
