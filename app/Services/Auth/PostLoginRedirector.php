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
            // TL team dashboard ships in Phase 2 (T-2.13) — generic dashboard until then.
            User::ROLE_TEAM_LEADER => 'dashboard',
            default => 'employee.profile',
        };
    }

    public function urlFor(User $user): string
    {
        return route($this->routeNameFor($user));
    }
}
