<?php

namespace App\Http\Controllers;

use App\Models\User;
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
        $user = auth()->user();
        $user?->loadMissing('valuationAssignments');

        return view('portal.home', [
            'snapshot' => $stats->snapshot($user),
            'moduleCards' => $this->homeModuleCards($user),
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
        abort_unless(AppModule::mayAccessModule($user, $module), 403);

        return view('portal.module-home', [
            'module' => $module,
            'stats' => $stats->monthSnapshot($user, $module),
            'monthSheets' => $user->isManager() ? $sheets->monthSheetsFor($user, $module) : collect(),
        ]);
    }

    /**
     * @return list<array{id: string, route: string, icon: string}>
     */
    private function homeModuleCards(?User $user): array
    {
        $cards = [];

        if (AppModule::mayAccessModule($user, AppModule::SERVICE)) {
            $cards[] = ['id' => 'service', 'route' => 'portal.service', 'icon' => 'bi-headset'];
        }
        if (AppModule::mayAccessModule($user, AppModule::PRODUCT)) {
            $cards[] = ['id' => 'product', 'route' => 'portal.product', 'icon' => 'bi-gear'];
        }

        $cards[] = ['id' => 'hr', 'route' => 'portal.hr', 'icon' => 'bi-people'];

        if ($user?->isManager()) {
            $cards[] = ['id' => 'settings', 'route' => 'admin.settings', 'icon' => 'bi-sliders'];
        }

        return $cards;
    }
}
