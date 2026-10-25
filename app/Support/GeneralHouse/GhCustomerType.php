<?php

namespace App\Support\GeneralHouse;

final class GhCustomerType
{
    public const B2C = 'B2C';

    public const B2B = 'B2B';

    /** @var list<string> */
    public const ALL = [self::B2C, self::B2B];
}
