<?php

namespace App\Support\GeneralHouse;

final class GhDebitAdjustmentLineType
{
    public const ADDITIONAL_ITEM = 'ADDITIONAL_ITEM';

    public const QUANTITY_INCREASE = 'QUANTITY_INCREASE';

    public const PRICE_INCREASE = 'PRICE_INCREASE';

    /** @var list<string> */
    public const ALL = [
        self::ADDITIONAL_ITEM,
        self::QUANTITY_INCREASE,
        self::PRICE_INCREASE,
    ];
}
