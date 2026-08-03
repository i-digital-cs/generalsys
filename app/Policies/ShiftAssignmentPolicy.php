<?php

namespace App\Policies;

use App\Models\ShiftAssignment;
use App\Models\User;

/**
 * Shift roster authorization (API §6 / T-2.14).
 * TEAM_LEADER: own team only. MANAGER: all. EMPLOYEE: never create/cancel.
 */
class ShiftAssignmentPolicy
{
    public function viewAny(User $authUser): bool
    {
        return $authUser->isManager() || $authUser->isTeamLeader();
    }

    public function create(User $authUser): bool
    {
        return $authUser->isManager() || $authUser->isTeamLeader();
    }

    public function cancel(User $authUser, ShiftAssignment $assignment): bool
    {
        return $this->update($authUser, $assignment);
    }

    public function update(User $authUser, ShiftAssignment $assignment): bool
    {
        if ($authUser->isManager()) {
            return true;
        }

        if (! $authUser->isTeamLeader()) {
            return false;
        }

        return (int) $assignment->user?->team_leader_id === (int) $authUser->id;
    }
}
