<?php

namespace App\Support\GeneralHouse;

final class GhFinancialCorrectionType
{
    public const PAYMENT_RECORDING_ERROR = 'PAYMENT_RECORDING_ERROR';

    /** @var list<string> */
    public const ALL = [
        self::PAYMENT_RECORDING_ERROR,
    ];
}
