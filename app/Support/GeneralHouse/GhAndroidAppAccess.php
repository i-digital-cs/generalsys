<?php

namespace App\Support\GeneralHouse;

use App\Models\User;
use App\Support\GnrlAppAccess;

/**
 * @deprecated Use {@see GnrlAppAccess}. Kept as a thin alias for existing call sites/tests.
 */
final class GhAndroidAppAccess
{
    public static function mayDownload(?User $user): bool
    {
        return GnrlAppAccess::mayDownload($user);
    }
}
