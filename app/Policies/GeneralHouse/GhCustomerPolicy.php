<?php

namespace App\Policies\GeneralHouse;

use App\Models\GeneralHouse\GhCustomer;
use App\Models\User;
use App\Support\GeneralHouse\GhCatalogAccess;

class GhCustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return GhCatalogAccess::mayManage($user);
    }

    public function view(User $user, GhCustomer $customer): bool
    {
        return GhCatalogAccess::mayManage($user);
    }

    public function create(User $user): bool
    {
        return GhCatalogAccess::mayManage($user);
    }

    public function update(User $user, GhCustomer $customer): bool
    {
        return GhCatalogAccess::mayManage($user);
    }

    public function delete(User $user, GhCustomer $customer): bool
    {
        return GhCatalogAccess::mayManage($user);
    }
}
