<?php

namespace App\Policies;

use App\Models\ShiftAssignment;
use App\Models\User;
use App\Services\Employees\TeamMembershipService;

/**
 * Shift roster authorization (API §6 / T-2.14).
 * TEAM_LEADER: own team only. MANAGER: all. EMPLOYEE: never create/cancel.
 */
class ShiftAssignmentPolicy
{
    public function __construct(private readonly TeamMembershipService $membership) {}

    public function viewAny(User $authUser): bool
    {
        return $authUser->canManageOperations() || $authUser->isTeamLeader();
    }

    public function create(User $authUser): bool
    {
        return $authUser->canManageOperations() || $authUser->isTeamLeader();
    }

    public function cancel(User $authUser, ShiftAssignment $assignment): bool
    {
        return $this->update($authUser, $assignment);
    }

    public function update(User $authUser, ShiftAssignment $assignment): bool
    {
        if ($authUser->canManageOperations()) {
            return true;
        }

        if (! $authUser->isTeamLeader()) {
            return false;
        }

        $owner = $assignment->user;

        return $owner instanceof User && $this->membership->leads($authUser, $owner);
    }
}
