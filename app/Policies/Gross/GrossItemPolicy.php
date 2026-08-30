<?php

namespace App\Policies\Gross;

use App\Models\Gross\GrossItem;
use App\Models\User;
use App\Support\GrossAccess;

class GrossItemPolicy
{
    public function viewAny(User $user): bool
    {
        return GrossAccess::mayAccessGross($user);
    }

    public function view(User $user, GrossItem $item): bool
    {
        return GrossAccess::mayAccessGross($user);
    }

    public function create(User $user): bool
    {
        return GrossAccess::mayManageMasterData($user);
    }

    public function update(User $user, GrossItem $item): bool
    {
        return GrossAccess::mayManageMasterData($user);
    }

    public function adjustStock(User $user): bool
    {
        return GrossAccess::mayAdjustStock($user);
    }
}
