<?php

namespace App\Policies\Gross;

use App\Models\Gross\GrossShippingCompany;
use App\Models\User;
use App\Support\GrossAccess;

class GrossShippingCompanyPolicy
{
    public function viewAny(User $user): bool
    {
        return GrossAccess::mayAccessGross($user);
    }

    public function view(User $user, GrossShippingCompany $company): bool
    {
        return GrossAccess::mayAccessGross($user);
    }

    public function create(User $user): bool
    {
        return GrossAccess::mayManageMasterData($user);
    }

    public function update(User $user, GrossShippingCompany $company): bool
    {
        return GrossAccess::mayManageMasterData($user);
    }

    public function delete(User $user, GrossShippingCompany $company): bool
    {
        return GrossAccess::mayManageMasterData($user);
    }
}
