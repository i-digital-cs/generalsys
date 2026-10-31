<?php

namespace App\Support\GeneralHouse;

final class GhInvoiceAdjustmentType
{
    public const INVOICE_DISCOUNT = 'INVOICE_DISCOUNT';

    public const MIN_ORDER_SURCHARGE = 'MIN_ORDER_SURCHARGE';

    /** @var list<string> */
    public const ALL = [self::INVOICE_DISCOUNT, self::MIN_ORDER_SURCHARGE];
}
