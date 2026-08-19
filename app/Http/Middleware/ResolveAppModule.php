<?php

namespace App\Http\Middleware;

use App\Support\AppModule;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class ResolveAppModule
{
    public function handle(Request $request, Closure $next): Response
    {
        $module = AppModule::fromRequest($request);

        View::share('appModule', $module);
        View::share('showHrSidebar', AppModule::showsSidebar($module));

        return $next($request);
    }
}
