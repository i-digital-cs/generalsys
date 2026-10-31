<?php

namespace App\Support\GeneralHouse;

final class GhOrderStatus
{
    public const LATER = 'LATER';

    public const PICKUP = 'PICKUP';

    public const WORKING = 'WORKING';

    public const DELIVERY = 'DELIVERY';

    public const COMPLETED = 'COMPLETED';

    public const CANCELLED = 'CANCELLED';

    /** @var list<string> */
    public const ALL = [
        self::LATER,
        self::PICKUP,
        self::WORKING,
        self::DELIVERY,
        self::COMPLETED,
        self::CANCELLED,
    ];

    /** Statuses where operational edit is still allowed (pre physical pickup). */
    public static function isPrePickup(string $status): bool
    {
        return in_array($status, [self::LATER, self::PICKUP], true);
    }
}
