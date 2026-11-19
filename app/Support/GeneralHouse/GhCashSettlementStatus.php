<?php

namespace App\Support\GeneralHouse;

/**
 * Driver-to-company settlement status of driver-collected cash (never the invoice payment status).
 * Always derived from collections and effective (non-reversed) allocations.
 */
final class GhCashSettlementStatus
{
    public const NOT_APPLICABLE = 'NOT_APPLICABLE';

    public const NOT_SETTLED = 'NOT_SETTLED';

    public const PARTIAL = 'PARTIAL';

    public const SETTLED = 'SETTLED';

    /** @var list<string> */
    public const ALL = [self::NOT_APPLICABLE, self::NOT_SETTLED, self::PARTIAL, self::SETTLED];

    public static function derive(string $collected, string $settled): string
    {
        if (GhMoney::cmp($collected, '0') <= 0) {
            return self::NOT_APPLICABLE;
        }
        if (GhMoney::cmp($settled, '0') <= 0) {
            return self::NOT_SETTLED;
        }

        return GhMoney::cmp($settled, $collected) >= 0 ? self::SETTLED : self::PARTIAL;
    }

    public static function badge(string $status): string
    {
        return match ($status) {
            self::SETTLED => 'success',
            self::PARTIAL => 'warning',
            self::NOT_SETTLED => 'danger',
            default => 'secondary',
        };
    }
}
