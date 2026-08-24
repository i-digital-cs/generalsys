<?php

namespace App\Services\Auth;

use App\Models\User;

/**
 * After login: company portal home (T-9.1).
 * HR tab / impersonation: existing role dashboard (MASTER_CONTEXT §3).
 */
class PostLoginRedirector
{
    public function routeNameFor(User $user): string
    {
        return 'portal.home';
    }

    public function urlFor(User $user): string
    {
        return route($this->routeNameFor($user));
    }

    public function hrRouteNameFor(User $user): string
    {
        return match ($user->role) {
            User::ROLE_MANAGER => 'admin.dashboard',
            User::ROLE_ASSISTANT => 'admin.dashboard',
            User::ROLE_TEAM_LEADER => 'team-leader.dashboard',
            User::ROLE_DRIVER => 'employee.clock',
            default => 'employee.dashboard',
        };
    }

    public function hrUrlFor(User $user): string
    {
        return route($this->hrRouteNameFor($user));
    }
}
