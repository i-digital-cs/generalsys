<?php

namespace App\Support\GeneralHouse;

final class GhDebitAdjustmentReason
{
    public const ADDITIONAL_ITEMS = 'ADDITIONAL_ITEMS';

    public const QUANTITY_CORRECTION = 'QUANTITY_CORRECTION';

    public const PRICE_CORRECTION = 'PRICE_CORRECTION';

    public const OTHER = 'OTHER';

    /** @var list<string> */
    public const ALL = [
        self::ADDITIONAL_ITEMS,
        self::QUANTITY_CORRECTION,
        self::PRICE_CORRECTION,
        self::OTHER,
    ];
}
