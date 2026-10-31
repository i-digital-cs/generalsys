<?php

namespace App\Policies\GeneralHouse;

use App\Models\GeneralHouse\GhOrder;
use App\Models\User;
use App\Support\GeneralHouse\GhCatalogAccess;

class GhOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return GhCatalogAccess::mayManage($user);
    }

    public function view(User $user, GhOrder $order): bool
    {
        return GhCatalogAccess::mayManage($user);
    }

    public function create(User $user): bool
    {
        return GhCatalogAccess::mayManage($user);
    }

    public function update(User $user, GhOrder $order): bool
    {
        return GhCatalogAccess::mayManage($user);
    }

    public function delete(User $user, GhOrder $order): bool
    {
        return false;
    }
}
