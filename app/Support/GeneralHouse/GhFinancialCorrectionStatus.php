<?php

namespace App\Support\GeneralHouse;

final class GhFinancialCorrectionStatus
{
    public const REQUESTED = 'REQUESTED';

    public const PENDING_APPROVAL = 'PENDING_APPROVAL';

    public const APPROVED = 'APPROVED';

    public const EXECUTED = 'EXECUTED';

    public const REJECTED = 'REJECTED';

    public const CANCELLED = 'CANCELLED';

    /** @var list<string> */
    public const ALL = [
        self::REQUESTED,
        self::PENDING_APPROVAL,
        self::APPROVED,
        self::EXECUTED,
        self::REJECTED,
        self::CANCELLED,
    ];
}
