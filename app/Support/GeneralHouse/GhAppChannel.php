<?php

namespace App\Support\GeneralHouse;

final class GhAppChannel
{
    public const TEST = 'test';

    public const LIVE = 'live';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::TEST, self::LIVE];
    }

    public static function isValid(string $channel): bool
    {
        return in_array($channel, self::all(), true);
    }

    /**
     * Trusted channel for this server deployment — never trust the mobile client.
     */
    public static function trusted(): string
    {
        $configured = strtolower((string) config('general_house.android_app.channel', self::TEST));

        return self::isValid($configured) ? $configured : self::TEST;
    }
}
