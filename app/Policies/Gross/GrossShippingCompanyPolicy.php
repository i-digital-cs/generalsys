<?php

namespace App\Policies\Gross;

use App\Models\Gross\GrossShippingCompany;
use App\Models\User;
use App\Support\GrossAccess;

class GrossShippingCompanyPolicy
{
    public function viewAny(User $user): bool
    {
        return GrossAccess::mayPage($user, 'shipping');
    }

    public function view(User $user, GrossShippingCompany $company): bool
    {
        return GrossAccess::mayPage($user, 'shipping');
    }

    public function create(User $user): bool
    {
        return GrossAccess::mayPage($user, 'shipping');
    }

    public function update(User $user, GrossShippingCompany $company): bool
    {
        return GrossAccess::mayPage($user, 'shipping');
    }

    public function delete(User $user, GrossShippingCompany $company): bool
    {
        return GrossAccess::mayPage($user, 'shipping');
    }
}
