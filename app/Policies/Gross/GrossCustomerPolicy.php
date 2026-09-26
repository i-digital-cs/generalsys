<?php

namespace App\Policies\Gross;

use App\Models\Gross\GrossCustomer;
use App\Models\User;
use App\Support\GrossAccess;

class GrossCustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return GrossAccess::mayPage($user, 'customers');
    }

    public function view(User $user, GrossCustomer $customer): bool
    {
        return GrossAccess::mayPage($user, 'customers');
    }

    public function create(User $user): bool
    {
        return GrossAccess::mayPage($user, 'customers');
    }

    public function update(User $user, GrossCustomer $customer): bool
    {
        return GrossAccess::mayPage($user, 'customers');
    }

    public function delete(User $user, GrossCustomer $customer): bool
    {
        return GrossAccess::mayPage($user, 'customers');
    }
}
