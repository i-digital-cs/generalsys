<?php

namespace App\Http\Controllers\GeneralHouse;

use App\Http\Controllers\Controller;
use App\Http\Requests\GeneralHouse\GhDriverCashFilterRequest;
use App\Models\User;
use App\Services\GeneralHouse\Payments\GhDriverCashActivityService;
use App\Services\GeneralHouse\Payments\GhDriverCashDashboardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DriverCashCustodyController extends Controller
{
    public function __construct(
        private readonly GhDriverCashDashboardService $dashboard,
        private readonly GhDriverCashActivityService $activity,
    ) {}

    public function index(GhDriverCashFilterRequest $request): View|RedirectResponse
    {
        $driverId = $request->validated('driver_id');
        if ($driverId !== null && User::query()->whereKey($driverId)->where('role', User::ROLE_DRIVER)->exists()) {
            return redirect()->route('gh.driver-cash.show', (int) $driverId);
        }

        return view('gh.driver-cash.index', [
            'drivers' => $this->dashboard->driversOverview($request->validated('q')),
            'driverOptions' => $this->dashboard->driverOptions(),
            'q' => (string) $request->validated('q'),
        ]);
    }

    public function show(GhDriverCashFilterRequest $request, User $driver): View
    {
        abort_unless($driver->isDriver(), 404);
        $tab = $request->tab();
        $period = $request->period();
        $asOf = $request->validated('as_of');

        return view('gh.driver-cash.show', [
            'driver' => $driver,
            'driverOptions' => $this->dashboard->driverOptions(),
            'summary' => $this->dashboard->summary($driver),
            'activity' => $this->dashboard->periodActivity($driver, $period),
            'asOf' => $asOf === null ? null : ['date' => $asOf, 'outstanding' => $this->activity->outstandingAsOf($driver, $asOf)],
            'period' => $period,
            'tab' => $tab,
            'tabData' => $this->tabData($tab, $driver, $period),
            'displayTz' => $period->timezone,
        ]);
    }

    private function tabData(string $tab, User $driver, $period): mixed
    {
        return match ($tab) {
            'outstanding' => $this->activity->outstandingCollections($driver),
            'settlements' => $this->activity->settlements(['driver_id' => $driver->id], $period),
            'adjustments' => $this->activity->adjustments($driver, $period),
            'ledger' => $this->activity->ledger($driver, $period),
            default => $this->activity->collections($driver, $period),
        };
    }
}
