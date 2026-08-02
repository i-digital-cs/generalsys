<?php

namespace App\Policies;

use App\Models\User;

/**
 * Employee-management authorization.
 *
 * PRIVACY (MASTER_CONTEXT §1.8): salary/rates and employee management are
 * MANAGER-only. TEAM_LEADER gets NO access here — not even to team members.
 */
class EmployeePolicy
{
    public function viewAny(User $authUser): bool
    {
        return $authUser->isManager();
    }

    /**
     * Viewing a single employee's non-financial profile: MANAGER or the
     * employee themselves (self profile). TEAM_LEADER: never.
     */
    public function view(User $authUser, User $employee): bool
    {
        return $authUser->isManager() || $authUser->is($employee);
    }

    public function create(User $authUser): bool
    {
        return $authUser->isManager();
    }

    public function update(User $authUser, User $employee): bool
    {
        return $authUser->isManager();
    }

    /**
     * Rates/salary editing — MANAGER only (system privacy rule #1).
     */
    public function updateRates(User $authUser, User $employee): bool
    {
        return $authUser->isManager();
    }

    /**
     * Document download: MANAGER or the owning employee.
     */
    public function downloadDocument(User $authUser, User $employee): bool
    {
        return $authUser->isManager() || $authUser->is($employee);
    }
}
