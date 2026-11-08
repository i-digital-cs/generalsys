<?php

namespace App\Policies\GeneralHouse;

use App\Models\GeneralHouse\GhProduct;
use App\Models\User;
use App\Support\GeneralHouse\GhCatalogAccess;
use App\Support\GeneralHouse\GhOrderAccess;

class GhProductPolicy
{
    public function viewAny(User $user): bool
    {
        return GhCatalogAccess::mayManage($user);
    }

    public function view(User $user, GhProduct $product): bool
    {
        return GhCatalogAccess::mayManage($user);
    }

    /** Thumbnail stream for pickers / invoice catalog (not the catalog admin UI). */
    public function viewImage(User $user, GhProduct $product): bool
    {
        return GhCatalogAccess::mayManage($user)
            || GhOrderAccess::mayUseDriverWorkspace($user);
    }

    public function create(User $user): bool
    {
        return GhCatalogAccess::mayManage($user);
    }

    public function update(User $user, GhProduct $product): bool
    {
        return GhCatalogAccess::mayManage($user);
    }

    public function delete(User $user, GhProduct $product): bool
    {
        return GhCatalogAccess::mayManage($user);
    }
}
