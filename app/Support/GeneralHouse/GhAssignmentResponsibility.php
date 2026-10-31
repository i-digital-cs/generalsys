<?php

namespace App\Support\GeneralHouse;

final class GhAssignmentResponsibility
{
    public const PICKUP = 'PICKUP';

    public const DELIVERY = 'DELIVERY';

    /** @var list<string> */
    public const ALL = [self::PICKUP, self::DELIVERY];
}
