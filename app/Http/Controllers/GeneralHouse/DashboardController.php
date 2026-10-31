<?php

namespace App\Http\Controllers\GeneralHouse;

use App\Http\Controllers\Controller;
use App\Http\Requests\GeneralHouse\GhReportFilterRequest;
use App\Models\GeneralHouse\GhCustomer;
use App\Models\GeneralHouse\GhZone;
use App\Models\User;
use App\Services\GeneralHouse\Reporting\GhDashboardService;
use App\Support\GeneralHouse\GhCatalogAccess;
use App\Support\GeneralHouse\GhReportFilters;
use App\Support\GeneralHouse\GhReportPeriod;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly GhDashboardService $dashboard,
    ) {}

    public function __invoke(GhReportFilterRequest $request): View
    {
        abort_unless(GhCatalogAccess::mayManage($request->user()), 403);

        $filters = GhReportFilters::fromValidated(array_merge(
            ['preset' => GhReportPeriod::PRESET_LAST_30],
            $request->validated(),
        ));

        $data = $this->dashboard->build($filters);

        return view('gh.dashboard.index', array_merge($data, [
            'customers' => GhCustomer::query()->orderBy('name_en')->limit(500)->get(['id', 'name_ar', 'name_en', 'type']),
            'zones' => GhZone::query()->orderBy('sort_order')->orderBy('name_en')->get(['id', 'name_ar', 'name_en']),
            'driverOptions' => User::query()->where('role', 'DRIVER')->where('status', 'ACTIVE')
                ->orderBy('name_en')->get(['id', 'name_ar', 'name_en']),
            'displayTz' => $filters->period->timezone,
        ]));
    }
}
