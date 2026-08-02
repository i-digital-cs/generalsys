<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Role gate: usage `role:MANAGER` or `role:MANAGER,TEAM_LEADER` on routes.
 * Guests are handled by the `auth` middleware before this runs.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        abort_if($user === null || ! in_array($user->role, $roles, true), 403);

        return $next($request);
    }
}
