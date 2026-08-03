<?php

namespace App\Services\Auth;

use App\Models\User;

/**
 * Decides where each role lands after login (MASTER_CONTEXT §3 role matrix).
 */
class PostLoginRedirector
{
    public function routeNameFor(User $user): string
    {
        return match ($user->role) {
            User::ROLE_MANAGER => 'admin.dashboard',
            User::ROLE_TEAM_LEADER => 'team-leader.dashboard',
            default => 'employee.dashboard',
        };
    }

    public function urlFor(User $user): string
    {
        return route($this->routeNameFor($user));
    }
}
