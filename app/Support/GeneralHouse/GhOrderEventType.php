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

    /** Management correction (forward/backward); payload.source = MANUAL. */
    public const MANUAL_STATUS_TRANSITION = 'MANUAL_STATUS_TRANSITION';

    /** Manager cleared auto_promote_held so scheduler may promote again. */
    public const AUTO_PROMOTE_RESUMED = 'AUTO_PROMOTE_RESUMED';

    public const PICKUP_WINDOW_CHANGED = 'PICKUP_WINDOW_CHANGED';

    public const PICKUP_PROMOTED = 'PICKUP_PROMOTED';

    public const PICKUP_CONFIRMED = 'PICKUP_CONFIRMED';

    public const DELIVERY_READY = 'DELIVERY_READY';

    public const DELIVERY_CONFIRMED = 'DELIVERY_CONFIRMED';

    public const INVOICE_ISSUED = 'INVOICE_ISSUED';

    public const ORDER_CANCELLED = 'ORDER_CANCELLED';

    public const PICKUP_FAILED = 'PICKUP_FAILED';

    public const DELIVERY_FAILED = 'DELIVERY_FAILED';

    public const PICKUP_RESCHEDULED = 'PICKUP_RESCHEDULED';
}
