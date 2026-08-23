<?php

namespace App\Http\Controllers;

use App\Services\Auth\PostLoginRedirector;
use App\Services\Portal\PortalStatsService;
use App\Services\Valuation\ValuationSheetService;
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

    public function service(ValuationStatsService $stats, ValuationSheetService $sheets): View
    {
        return $this->moduleHome(AppModule::SERVICE, $stats, $sheets);
    }

    public function product(ValuationStatsService $stats, ValuationSheetService $sheets): View
    {
        return $this->moduleHome(AppModule::PRODUCT, $stats, $sheets);
    }

    private function moduleHome(string $module, ValuationStatsService $stats, ValuationSheetService $sheets): View
    {
        $user = auth()->user();

        return view('portal.module-home', [
            'module' => $module,
            'stats' => $stats->monthSnapshot($user, $module),
            'monthSheets' => $user->isManager() ? $sheets->monthSheetsFor($user, $module) : collect(),
        ]);
    }
}
