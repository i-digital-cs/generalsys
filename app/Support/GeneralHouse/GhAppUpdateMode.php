<?php

namespace App\Support\GeneralHouse;

final class GhAppUpdateMode
{
    public const OPTIONAL = 'OPTIONAL';

    public const REQUIRED_BY_MINIMUM = 'REQUIRED_BY_MINIMUM';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::OPTIONAL, self::REQUIRED_BY_MINIMUM];
    }

    public static function isValid(string $mode): bool
    {
        return in_array($mode, self::all(), true);
    }
}
