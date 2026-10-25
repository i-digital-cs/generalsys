<?php

namespace App\Policies\GeneralHouse;

use App\Models\GeneralHouse\GhProductOffering;
use App\Models\User;
use App\Support\GeneralHouse\GhCatalogAccess;

class GhProductOfferingPolicy
{
    public function viewAny(User $user): bool
    {
        return GhCatalogAccess::mayManage($user);
    }

    public function view(User $user, GhProductOffering $offering): bool
    {
        return GhCatalogAccess::mayManage($user);
    }

    public function create(User $user): bool
    {
        return GhCatalogAccess::mayManage($user);
    }

    public function update(User $user, GhProductOffering $offering): bool
    {
        return GhCatalogAccess::mayManage($user);
    }

    public function delete(User $user, GhProductOffering $offering): bool
    {
        return GhCatalogAccess::mayManage($user);
    }
}
