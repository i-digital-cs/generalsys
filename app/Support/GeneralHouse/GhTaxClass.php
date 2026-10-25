<?php

namespace App\Support\GeneralHouse;

/**
 * Offering tax classification metadata (Phase 1).
 * Invoice VAT engine is Phase 5 — these values are stored and validated only.
 */
final class GhTaxClass
{
    public const STANDARD_RATED = 'STANDARD_RATED';

    public const ZERO_RATED = 'ZERO_RATED';

    public const EXEMPT = 'EXEMPT';

    public const OUT_OF_SCOPE = 'OUT_OF_SCOPE';

    /** @var list<string> */
    public const ALL = [
        self::STANDARD_RATED,
        self::ZERO_RATED,
        self::EXEMPT,
        self::OUT_OF_SCOPE,
    ];
}
