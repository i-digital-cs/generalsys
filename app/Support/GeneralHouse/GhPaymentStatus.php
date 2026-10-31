<?php

namespace App\Support\GeneralHouse;

final class GhPaymentStatus
{
    public const PENDING = 'PENDING';

    public const PENDING_REVIEW = 'PENDING_REVIEW';

    public const CONFIRMED = 'CONFIRMED';

    public const REJECTED = 'REJECTED';

    public const CANCELLED = 'CANCELLED';

    /** @var list<string> */
    public const ALL = [
        self::PENDING,
        self::PENDING_REVIEW,
        self::CONFIRMED,
        self::REJECTED,
        self::CANCELLED,
    ];

    public static function affectsBalance(string $status): bool
    {
        return $status === self::CONFIRMED;
    }
}
