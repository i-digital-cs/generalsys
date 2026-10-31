<?php

namespace App\Support\GeneralHouse;

final class GhAttemptReason
{
    public const CUSTOMER_UNAVAILABLE = 'CUSTOMER_UNAVAILABLE';

    public const ADDRESS_ISSUE = 'ADDRESS_ISSUE';

    public const PICKUP_NOT_READY = 'PICKUP_NOT_READY';

    public const DELIVERY_UNSUCCESSFUL = 'DELIVERY_UNSUCCESSFUL';

    public const OTHER = 'OTHER';

    /** @var list<string> */
    public const PICKUP = [
        self::CUSTOMER_UNAVAILABLE,
        self::ADDRESS_ISSUE,
        self::PICKUP_NOT_READY,
        self::OTHER,
    ];

    /** @var list<string> */
    public const DELIVERY = [
        self::CUSTOMER_UNAVAILABLE,
        self::ADDRESS_ISSUE,
        self::DELIVERY_UNSUCCESSFUL,
        self::OTHER,
    ];
}
