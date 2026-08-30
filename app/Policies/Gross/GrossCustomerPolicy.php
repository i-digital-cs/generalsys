<?php

namespace App\Policies\Gross;

use App\Models\Gross\GrossCustomer;
use App\Models\User;
use App\Support\GrossAccess;

class GrossCustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return GrossAccess::mayAccessGross($user);
    }

    public function view(User $user, GrossCustomer $customer): bool
    {
        return GrossAccess::mayAccessGross($user);
    }

    public function create(User $user): bool
    {
        return GrossAccess::mayManageMasterData($user);
    }

    public function update(User $user, GrossCustomer $customer): bool
    {
        return GrossAccess::mayManageMasterData($user);
    }

    public function delete(User $user, GrossCustomer $customer): bool
    {
        return GrossAccess::mayManageMasterData($user);
    }
}
