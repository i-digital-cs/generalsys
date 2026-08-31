<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Inactive users may only view the inactive notice, switch language, leave impersonation, or log out.
 */
class EnsureUserIsActive
{
    /** @var list<string> */
    private const ALLOWED_ROUTE_NAMES = [
        'account.inactive',
        'logout',
        'locale',
        'impersonation.leave',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user instanceof User && ! $user->isActive()) {
            $routeName = $request->route()?->getName();
            if (! in_array($routeName, self::ALLOWED_ROUTE_NAMES, true)) {
                return redirect()->route('account.inactive');
            }
        }

        return $next($request);
    }
}
