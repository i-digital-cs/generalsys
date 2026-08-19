<?php

namespace App\Http\Controllers;

use App\Services\Auth\PostLoginRedirector;
use App\Services\Portal\PortalStatsService;
use App\Services\Valuation\ValuationStatsService;
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

    public function service(ValuationStatsService $stats): View
    {
        return view('portal.module-home', [
            'module' => AppModule::SERVICE,
            'stats' => $stats->monthSnapshot(auth()->user(), AppModule::SERVICE),
        ]);
    }

    public function product(ValuationStatsService $stats): View
    {
        return view('portal.module-home', [
            'module' => AppModule::PRODUCT,
            'stats' => $stats->monthSnapshot(auth()->user(), AppModule::PRODUCT),
        ]);
    }
}
