<?php

namespace App\Support\GeneralHouse;

final class GhRefundStatus
{
    public const REQUESTED = 'REQUESTED';

    public const PENDING_APPROVAL = 'PENDING_APPROVAL';

    public const APPROVED = 'APPROVED';

    public const PAID_OUT = 'PAID_OUT';

    public const REJECTED = 'REJECTED';

    public const CANCELLED = 'CANCELLED';

    /** @var list<string> */
    public const ALL = [
        self::REQUESTED,
        self::PENDING_APPROVAL,
        self::APPROVED,
        self::PAID_OUT,
        self::REJECTED,
        self::CANCELLED,
    ];

    /** Statuses that reserve refundable capacity before payout. */
    public const RESERVING = [
        self::REQUESTED,
        self::PENDING_APPROVAL,
        self::APPROVED,
    ];
}
