<?php

namespace App\Support\GeneralHouse;

final class GhOrderEventType
{
    public const ORDER_CREATED = 'ORDER_CREATED';

    public const ORDER_UPDATED = 'ORDER_UPDATED';

    public const LINES_REPLACED = 'LINES_REPLACED';

    public const DRIVER_ASSIGNED = 'DRIVER_ASSIGNED';

    public const DRIVER_REASSIGNED = 'DRIVER_REASSIGNED';

    public const STATUS_CHANGED = 'STATUS_CHANGED';

    public const PICKUP_WINDOW_CHANGED = 'PICKUP_WINDOW_CHANGED';
}
