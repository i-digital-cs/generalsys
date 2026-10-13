<?php

namespace App\Policies;

use App\Models\Permission;
use App\Models\User;
use App\Services\Permissions\PermissionRequestService;

/**
 * HR leave/late/absence permission requests (not ACL).
 * Mirrors PermissionRequestService::actorMayDecide / actorMayForce — no widening.
 */
class PermissionPolicy
{
    public function __construct(
        private readonly PermissionRequestService $permissions,
    ) {}

    /** Owner may view their own request. */
    public function view(User $user, Permission $permission): bool
    {
        return (int) $permission->user_id === (int) $user->id
            || $this->permissions->actorMayDecide($user, $permission);
    }

    /** TL (team) or Manager — accept/reject PENDING. */
    public function decide(User $user, Permission $permission): bool
    {
        return $this->permissions->actorMayDecide($user, $permission);
    }

    /** Self, TL (team), or Manager — REJECTED → FORCED. */
    public function force(User $user, Permission $permission): bool
    {
        return $this->permissions->actorMayForce($user, $permission);
    }
}
