<?php

namespace App\Policies;

use App\Models\PenaltyRule;
use App\Models\User;

/** Penalty rules: Manager only (AGENTS §5.1 — TL never sees money/config rates). */
class PenaltyRulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isManager();
    }

    public function view(User $user, PenaltyRule $penaltyRule): bool
    {
        return $user->isManager();
    }

    public function create(User $user): bool
    {
        return $user->isManager();
    }

    public function update(User $user, PenaltyRule $penaltyRule): bool
    {
        return $user->isManager();
    }
}
