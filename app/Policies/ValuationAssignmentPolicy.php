<?php

namespace App\Policies;

use App\Models\User;

class ValuationAssignmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isManager();
    }

    public function update(User $user): bool
    {
        return $user->isManager();
    }
}
