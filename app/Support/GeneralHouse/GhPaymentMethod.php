<?php

namespace App\Support\GeneralHouse;

final class GhPaymentMethod
{
    public const CASH = 'CASH';

    public const ONLINE = 'ONLINE';

    /** Reserved — not approved for GH-6 (see GH6_BUSINESS_DECISION_GATE.md). */
    public const BANK = 'BANK';

    /** @var list<string> */
    public const APPROVED = [self::CASH, self::ONLINE];

    /** @var list<string> */
    public const ALL = [self::CASH, self::ONLINE, self::BANK];
}
