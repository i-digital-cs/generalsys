<?php

namespace App\Policies\GeneralHouse;

use App\Models\User;
use App\Support\GeneralHouse\GhCatalogAccess;

class GhDriverCashCustodyPolicy
{
    public function viewAny(User $user): bool
    {
        return GhCatalogAccess::mayManage($user);
    }

    public function settle(User $user): bool
    {
        return GhCatalogAccess::mayManage($user);
    }
}
