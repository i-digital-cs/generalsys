<?php

namespace App\Http\Controllers\GeneralHouse;

use App\Http\Controllers\Controller;
use App\Http\Requests\GeneralHouse\SettleGhDriverCashRequest;
use App\Models\User;
use App\Services\GeneralHouse\Payments\GhDriverCashCustodyService;
use App\Support\GeneralHouse\GhCatalogAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DriverCashCustodyController extends Controller
{
    public function __construct(
        private readonly GhDriverCashCustodyService $custody,
    ) {}

    public function index(Request $request): View
    {
        abort_unless(GhCatalogAccess::mayManage($request->user()), 403);

        $driverId = $request->integer('driver_id') ?: null;
        $drivers = $this->custody->outstandingDriversReport();
        $collections = $this->custody->paginateCollections($driverId);
        $settlements = $this->custody->paginateSettlements($driverId);

        $selectedDriver = $driverId
            ? User::query()->find($driverId)
            : null;
        $snapshot = $selectedDriver
            ? $this->custody->snapshotFor($selectedDriver)
            : null;

        return view('gh.driver-cash.index', [
            'outstandingDrivers' => $drivers,
            'collections' => $collections,
            'settlements' => $settlements,
            'selectedDriver' => $selectedDriver,
            'snapshot' => $snapshot,
            'driverId' => $driverId,
        ]);
    }

    public function show(Request $request, User $driver): View
    {
        abort_unless(GhCatalogAccess::mayManage($request->user()), 403);
        abort_unless($driver->isDriver(), 404);

        return view('gh.driver-cash.show', [
            'driver' => $driver,
            'snapshot' => $this->custody->snapshotFor($driver),
            'collections' => $this->custody->paginateCollections($driver->id),
            'settlements' => $this->custody->paginateSettlements($driver->id),
        ]);
    }

    public function settle(SettleGhDriverCashRequest $request, User $driver): RedirectResponse
    {
        abort_unless($driver->isDriver(), 404);

        $this->custody->recordSettlement($driver, $request->user(), $request->validated());

        return redirect()
            ->route('gh.driver-cash.show', $driver)
            ->with('success', __('gh.flash.cash_settlement_recorded'));
    }
}
