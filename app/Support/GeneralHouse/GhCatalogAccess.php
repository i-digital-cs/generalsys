<?php

namespace App\Support\GeneralHouse;

use App\Models\User;

/**
 * Phase 1: catalog administration is Manager-only.
 * Fine-grained gh_user_pages ACL is deferred to a later phase.
 */
final class GhCatalogAccess
{
    public static function mayManage(?User $user): bool
    {
        return $user !== null && $user->isManager();
    }
}
