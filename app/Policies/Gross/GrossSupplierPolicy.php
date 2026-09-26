<?php

namespace App\Policies\Gross;

use App\Models\Gross\GrossSupplier;
use App\Models\User;
use App\Support\GrossAccess;

class GrossSupplierPolicy
{
    public function viewAny(User $user): bool
    {
        return GrossAccess::mayPage($user, 'suppliers');
    }

    public function view(User $user, GrossSupplier $supplier): bool
    {
        return GrossAccess::mayPage($user, 'suppliers');
    }

    public function create(User $user): bool
    {
        return GrossAccess::mayPage($user, 'suppliers');
    }

    public function update(User $user, GrossSupplier $supplier): bool
    {
        return GrossAccess::mayPage($user, 'suppliers');
    }

    public function delete(User $user, GrossSupplier $supplier): bool
    {
        return GrossAccess::mayPage($user, 'suppliers');
    }
}
