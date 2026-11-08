<?php

namespace App\Support\GeneralHouse;

final class GhAppPlatform
{
    public const ANDROID = 'android';

    public const IOS = 'ios';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::ANDROID, self::IOS];
    }

    public static function isValid(string $platform): bool
    {
        return in_array($platform, self::all(), true);
    }
}
