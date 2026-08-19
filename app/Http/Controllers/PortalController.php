<?php

namespace App\Http\Controllers;

use App\Services\Auth\PostLoginRedirector;
use App\Services\Portal\PortalStatsService;
use App\Support\AppModule;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class PortalController extends Controller
{
    public function home(PortalStatsService $stats): View
    {
        return view('portal.home', [
            'snapshot' => $stats->snapshot(auth()->user()),
        ]);
    }

    public function enterHr(PostLoginRedirector $redirector): RedirectResponse
    {
        return redirect()->to($redirector->hrUrlFor(auth()->user()));
    }

    public function service(): View
    {
        return view('portal.module', [
            'module' => AppModule::SERVICE,
        ]);
    }

    public function production(): View
    {
        return view('portal.module', [
            'module' => AppModule::PRODUCTION,
        ]);
    }
}
