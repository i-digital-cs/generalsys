<?php

namespace App\Support\GeneralHouse;

final class GhCreditNoteReason
{
    public const QUANTITY_ERROR = 'QUANTITY_ERROR';

    public const PRICE_ERROR = 'PRICE_ERROR';

    public const SERVICE_REDUCTION = 'SERVICE_REDUCTION';

    public const OTHER = 'OTHER';

    /** @var list<string> */
    public const ALL = [
        self::QUANTITY_ERROR,
        self::PRICE_ERROR,
        self::SERVICE_REDUCTION,
        self::OTHER,
    ];
}
