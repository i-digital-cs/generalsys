<?php

namespace App\Policies\GeneralHouse;

use App\Models\GeneralHouse\GhServiceCategory;
use App\Models\User;
use App\Support\GeneralHouse\GhCatalogAccess;

class GhServiceCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return GhCatalogAccess::mayManage($user);
    }

    public function view(User $user, GhServiceCategory $category): bool
    {
        return GhCatalogAccess::mayManage($user);
    }

    public function create(User $user): bool
    {
        return GhCatalogAccess::mayManage($user);
    }

    public function update(User $user, GhServiceCategory $category): bool
    {
        return GhCatalogAccess::mayManage($user);
    }

    public function delete(User $user, GhServiceCategory $category): bool
    {
        return GhCatalogAccess::mayManage($user);
    }
}
