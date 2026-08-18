<?php

namespace App\Policies;

use App\Models\User;

/**
 * Employee-management authorization.
 *
 * PRIVACY (MASTER_CONTEXT §1.8): TEAM_LEADER never sees salary/rates.
 * Manager and Assistant may manage staff records including salary.
 */
class EmployeePolicy
{
    public function viewAny(User $authUser): bool
    {
        return $authUser->canManageOperations();
    }

    /**
     * Viewing a single employee's non-financial profile: operations admin
     * or the employee themselves. TEAM_LEADER: never.
     */
    public function view(User $authUser, User $employee): bool
    {
        return $authUser->canManageOperations() || $authUser->is($employee);
    }

    public function create(User $authUser): bool
    {
        return $authUser->canManageOperations();
    }

    public function update(User $authUser, User $employee): bool
    {
        return $authUser->mayEditStaff($employee);
    }

    /**
     * Rates/salary editing — Manager or Assistant. TEAM_LEADER never.
     */
    public function updateRates(User $authUser, User $employee): bool
    {
        return $authUser->canManageRates();
    }

    /**
     * Document download: operations admin or the owning employee.
     */
    public function downloadDocument(User $authUser, User $employee): bool
    {
        return $authUser->canManageOperations() || $authUser->is($employee);
    }

    /**
     * Session switch into another account — Manager only. Feature flag is in ImpersonationService.
     */
    public function impersonate(User $authUser, User $employee): bool
    {
        return $authUser->isManager() && ! $authUser->is($employee);
    }
}
