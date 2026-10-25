<?php

namespace App\Policies\GeneralHouse;

use App\Models\GeneralHouse\GhTreatment;
use App\Models\User;
use App\Support\GeneralHouse\GhCatalogAccess;

class GhTreatmentPolicy
{
    public function viewAny(User $user): bool
    {
        return GhCatalogAccess::mayManage($user);
    }

    public function view(User $user, GhTreatment $treatment): bool
    {
        return GhCatalogAccess::mayManage($user);
    }

    public function create(User $user): bool
    {
        return GhCatalogAccess::mayManage($user);
    }

    public function update(User $user, GhTreatment $treatment): bool
    {
        return GhCatalogAccess::mayManage($user);
    }

    public function delete(User $user, GhTreatment $treatment): bool
    {
        return GhCatalogAccess::mayManage($user);
    }
}
