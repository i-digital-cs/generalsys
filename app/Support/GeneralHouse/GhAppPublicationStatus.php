<?php

namespace App\Support\GeneralHouse;

final class GhAppPublicationStatus
{
    public const UNPUBLISHED = 'UNPUBLISHED';

    public const PUBLISHED = 'PUBLISHED';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::UNPUBLISHED, self::PUBLISHED];
    }

    public static function isValid(string $status): bool
    {
        return in_array($status, self::all(), true);
    }
}
