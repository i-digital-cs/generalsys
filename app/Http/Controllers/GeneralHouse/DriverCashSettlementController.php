<?php

namespace App\Http\Controllers\GeneralHouse;

use App\Http\Controllers\Controller;
use App\Http\Requests\GeneralHouse\AllocateGhDriverCashSettlementRequest;
use App\Http\Requests\GeneralHouse\GhDriverCashFilterRequest;
use App\Http\Requests\GeneralHouse\ReverseGhDriverCashSettlementRequest;
use App\Http\Requests\GeneralHouse\SettleGhDriverCashRequest;
use App\Models\GeneralHouse\GhDriverCashSettlement;
use App\Models\User;
use App\Services\GeneralHouse\Payments\GhDriverCashActivityService;
use App\Services\GeneralHouse\Payments\GhDriverCashDashboardService;
use App\Services\GeneralHouse\Payments\GhDriverCashSettlementService;
use App\Support\GeneralHouse\GhCatalogAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DriverCashSettlementController extends Controller
{
    public function __construct(
        private readonly GhDriverCashSettlementService $settlements,
        private readonly GhDriverCashActivityService $activity,
        private readonly GhDriverCashDashboardService $dashboard,
    ) {}

    public function index(GhDriverCashFilterRequest $request): View
    {
        $period = $request->periodOrNull();

        return view('gh.driver-cash.settlements.index', [
            'settlements' => $this->activity->settlements($request->settlementFilters(), $period),
            'filters' => $request->settlementFilters(),
            'period' => $period,
            'driverOptions' => $this->dashboard->driverOptions(),
            'settlerOptions' => $this->dashboard->settlerOptions(),
            'displayTz' => (string) config('general_house.timezone', 'Asia/Dubai'),
        ]);
    }

    public function create(Request $request, User $driver): View
    {
        abort_unless(GhCatalogAccess::mayManage($request->user()), 403);
        abort_unless($driver->isDriver(), 404);

        return view('gh.driver-cash.settlements.create', [
            'driver' => $driver,
            'summary' => $this->dashboard->summary($driver),
            'collections' => $this->activity->outstandingCollections($driver),
            'limit' => GhDriverCashActivityService::OUTSTANDING_LIMIT,
            'displayTz' => (string) config('general_house.timezone', 'Asia/Dubai'),
        ]);
    }

    public function store(SettleGhDriverCashRequest $request, User $driver): RedirectResponse
    {
        abort_unless($driver->isDriver(), 404);
        $settlement = $this->settlements->settle($driver, $request->user(), $request->settlementData());

        return redirect()
            ->route('gh.driver-cash.settlements.show', $settlement)
            ->with('success', __('gh.flash.cash_settlement_recorded'));
    }

    public function show(Request $request, GhDriverCashSettlement $settlement): View
    {
        abort_unless(GhCatalogAccess::mayManage($request->user()), 403);
        $settlement->load([
            'driver:id,name_ar,name_en,role',
            'settler:id,name_ar,name_en',
            'reversal.reverser:id,name_ar,name_en',
            'allocations.collection.order:id,reference',
            'allocations.collection.order.invoice:id,order_id,number',
            'allocations.allocator:id,name_ar,name_en',
        ]);
        $mayAllocate = $this->settlements->mayAllocate($settlement);

        return view('gh.driver-cash.settlements.show', [
            'settlement' => $settlement,
            'unallocated' => $this->settlements->unallocatedFor($settlement),
            'mayAllocate' => $mayAllocate,
            'mayReverse' => $this->settlements->mayReverse($settlement, $request->user()),
            'outstanding' => $mayAllocate && $settlement->driver ? $this->activity->outstandingCollections($settlement->driver) : collect(),
            'displayTz' => (string) config('general_house.timezone', 'Asia/Dubai'),
        ]);
    }

    public function reverse(ReverseGhDriverCashSettlementRequest $request, GhDriverCashSettlement $settlement): RedirectResponse
    {
        $this->settlements->reverse($settlement, $request->user(), $request->validated());

        return redirect()
            ->route('gh.driver-cash.settlements.show', $settlement)
            ->with('success', __('gh.flash.cash_settlement_reversed'));
    }

    public function allocate(AllocateGhDriverCashSettlementRequest $request, GhDriverCashSettlement $settlement): RedirectResponse
    {
        $this->settlements->allocateUnallocated($settlement, $request->user(), $request->allocationData());

        return redirect()
            ->route('gh.driver-cash.settlements.show', $settlement)
            ->with('success', __('gh.flash.cash_settlement_allocated'));
    }
}
