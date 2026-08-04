<?php

namespace App\Policies;

use App\Models\Advance;
use App\Models\User;

/** Advances: Manager CRUD; employee may view own list only. TL never. */
class AdvancePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isManager();
    }

    public function viewOwn(User $user): bool
    {
        return $user->isEmployee();
    }

    public function view(User $user, Advance $advance): bool
    {
        if ($user->isManager()) {
            return true;
        }

        return $user->isEmployee() && $user->id === $advance->user_id;
    }

    public function create(User $user): bool
    {
        return $user->isManager();
    }

    public function update(User $user, Advance $advance): bool
    {
        return $user->isManager() && $advance->status === Advance::STATUS_ACTIVE;
    }
}
