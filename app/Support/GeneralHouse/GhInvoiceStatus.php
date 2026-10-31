<?php

namespace App\Support\GeneralHouse;

final class GhInvoiceStatus
{
    public const DRAFT = 'DRAFT';

    public const ISSUED = 'ISSUED';

    /** @var list<string> */
    public const ALL = [self::DRAFT, self::ISSUED];
}
