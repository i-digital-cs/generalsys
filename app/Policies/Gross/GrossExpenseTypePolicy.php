<?php

namespace App\Policies\Gross;

use App\Models\Gross\GrossExpenseType;
use App\Models\User;
use App\Support\GrossAccess;

class GrossExpenseTypePolicy
{
    public function viewAny(User $user): bool
    {
        return GrossAccess::mayManageMasterData($user);
    }

    public function create(User $user): bool
    {
        return GrossAccess::mayManageMasterData($user);
    }

    public function update(User $user, GrossExpenseType $type): bool
    {
        return GrossAccess::mayManageMasterData($user);
    }

    public function delete(User $user, GrossExpenseType $type): bool
    {
        return GrossAccess::mayManageMasterData($user);
    }
}
