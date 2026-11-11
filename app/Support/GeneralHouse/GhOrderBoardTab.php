<?php

namespace App\Support\GeneralHouse;

/**
 * UI board tabs for GH order management (V2 cards).
 *
 * These are presentation filters — not new persisted order statuses.
 * Mapping to {@see GhOrderStatus} is owned by GhOrderBoardService.
 */
final class GhOrderBoardTab
{
    public const LATER = 'later';

    public const ACTIONS = 'actions';

    public const PICKUP = 'pickup';

    public const WORKING = 'working';

    public const DELIVERY = 'delivery';

    public const COMPLETE = 'complete';

    /** @var list<string> */
    public const ALL = [
        self::LATER,
        self::ACTIONS,
        self::PICKUP,
        self::WORKING,
        self::DELIVERY,
        self::COMPLETE,
    ];

    public static function isValid(string $tab): bool
    {
        return in_array($tab, self::ALL, true);
    }

    public static function normalize(?string $tab): string
    {
        $tab = strtolower(trim((string) $tab));

        return self::isValid($tab) ? $tab : self::PICKUP;
    }
}
