<?php

namespace App\Support\GeneralHouse;

/**
 * Driver movement log types — exactly two; never combine.
 */
final class GhDriverMovementType
{
    public const PICKUP = 'PICKUP';

    public const DELIVERY = 'DELIVERY';

    /** @var list<string> */
    public const ALL = [self::PICKUP, self::DELIVERY];

    public static function isValid(string $type): bool
    {
        return in_array($type, self::ALL, true);
    }
}
