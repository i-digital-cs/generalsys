<?php

namespace App\Policies\Gross;

use App\Models\Gross\GrossSupplier;
use App\Models\User;
use App\Support\GrossAccess;

class GrossSupplierPolicy
{
    public function viewAny(User $user): bool
    {
        return GrossAccess::mayAccessGross($user);
    }

    public function view(User $user, GrossSupplier $supplier): bool
    {
        return GrossAccess::mayAccessGross($user);
    }

    public function create(User $user): bool
    {
        return GrossAccess::mayManageMasterData($user);
    }

    public function update(User $user, GrossSupplier $supplier): bool
    {
        return GrossAccess::mayManageMasterData($user);
    }

    public function delete(User $user, GrossSupplier $supplier): bool
    {
        return GrossAccess::mayManageMasterData($user);
    }
}
