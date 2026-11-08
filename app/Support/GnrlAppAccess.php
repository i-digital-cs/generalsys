<?php

namespace App\Support;

use App\Models\User;

/**
 * GNRL Operations is the full GeneralSys mobile shell — not GH-only.
 * Download: any active authenticated user. Version admin: Manager only.
 */
final class GnrlAppAccess
{
    public static function mayDownload(?User $user): bool
    {
        return $user !== null && $user->isActive();
    }

    public static function mayManageVersions(?User $user): bool
    {
        return $user !== null && $user->isActive() && $user->isManager();
    }
}
