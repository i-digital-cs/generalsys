<?php

namespace App\Policies\GeneralHouse;

use App\Models\GeneralHouse\GhZone;
use App\Models\User;
use App\Support\GeneralHouse\GhCatalogAccess;

class GhZonePolicy
{
    public function viewAny(User $user): bool
    {
        return GhCatalogAccess::mayManage($user);
    }

    public function view(User $user, GhZone $zone): bool
    {
        return GhCatalogAccess::mayManage($user);
    }

    public function create(User $user): bool
    {
        return GhCatalogAccess::mayManage($user);
    }

    public function update(User $user, GhZone $zone): bool
    {
        return GhCatalogAccess::mayManage($user);
    }

    public function delete(User $user, GhZone $zone): bool
    {
        return GhCatalogAccess::mayManage($user);
    }
}
