<?php

namespace App\Policies;

use App\Models\Advance;
use App\Models\User;

/** Advances: Manager + Accountant CRUD; employee/accountant may view own list. TL never. */
class AdvancePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAccessAdvances();
    }

    public function viewOwn(User $user): bool
    {
        return $user->isEmployee() || $user->isSales() || $user->isAccountant();
    }

    public function view(User $user, Advance $advance): bool
    {
        if ($user->canAccessAdvances()) {
            return true;
        }

        return ($user->isEmployee() || $user->isSales() || $user->isAccountant())
            && $user->id === $advance->user_id;
    }

    public function create(User $user): bool
    {
        return $user->canAccessAdvances();
    }

    public function update(User $user, Advance $advance): bool
    {
        return $user->canAccessAdvances() && $advance->status === Advance::STATUS_ACTIVE;
    }
}
