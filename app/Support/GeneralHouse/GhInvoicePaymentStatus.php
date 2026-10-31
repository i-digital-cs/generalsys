<?php

namespace App\Support\GeneralHouse;

/**
 * Derived invoice.payment_status values (GH-5 column).
 * PARTIAL matches existing schema; UI may label as partially paid.
 */
final class GhInvoicePaymentStatus
{
    public const UNPAID = 'UNPAID';

    public const PARTIAL = 'PARTIAL';

    public const PAID = 'PAID';

    /** @var list<string> */
    public const ALL = [self::UNPAID, self::PARTIAL, self::PAID];
}
