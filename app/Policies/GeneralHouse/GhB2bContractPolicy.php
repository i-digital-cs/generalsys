<?php

namespace App\Policies\GeneralHouse;

use App\Models\GeneralHouse\GhB2bContract;
use App\Models\User;
use App\Support\GeneralHouse\GhCatalogAccess;

class GhB2bContractPolicy
{
    public function viewAny(User $user): bool
    {
        return GhCatalogAccess::mayManage($user);
    }

    public function view(User $user, GhB2bContract $contract): bool
    {
        return GhCatalogAccess::mayManage($user);
    }

    public function create(User $user): bool
    {
        return GhCatalogAccess::mayManage($user);
    }

    public function update(User $user, GhB2bContract $contract): bool
    {
        return GhCatalogAccess::mayManage($user);
    }

    public function delete(User $user, GhB2bContract $contract): bool
    {
        return GhCatalogAccess::mayManage($user);
    }
}
