<?php

namespace App\Policies\Gross;

use App\Models\Gross\GrossItem;
use App\Models\User;
use App\Support\GrossAccess;

class GrossItemPolicy
{
    public function viewAny(User $user): bool
    {
        return GrossAccess::mayPage($user, 'items');
    }

    public function view(User $user, GrossItem $item): bool
    {
        return GrossAccess::mayPage($user, 'items');
    }

    public function create(User $user): bool
    {
        return GrossAccess::mayPage($user, 'items');
    }

    public function update(User $user, GrossItem $item): bool
    {
        return GrossAccess::mayPage($user, 'items');
    }

    public function adjustStock(User $user): bool
    {
        return GrossAccess::mayAdjustStock($user);
    }
}
