<?php

namespace App\Http\Controllers\GeneralHouse;

use App\Http\Controllers\Controller;
use App\Http\Requests\GeneralHouse\StoreGhZoneRequest;
use App\Http\Requests\GeneralHouse\UpdateGhZoneRequest;
use App\Models\GeneralHouse\GhZone;
use App\Services\GeneralHouse\Customers\GhZoneService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ZoneController extends Controller
{
    public function __construct(private readonly GhZoneService $zones) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', GhZone::class);
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => (string) $request->query('status', ''),
        ];

        return view('gh.zones.index', [
            'rows' => $this->zones->paginate($filters),
            'filters' => $filters,
        ]);
    }

    public function store(StoreGhZoneRequest $request): RedirectResponse
    {
        $this->zones->create($request->validated());

        return redirect()->route('gh.zones.index')->with('success', __('gh.flash.zone_created'));
    }

    public function update(UpdateGhZoneRequest $request, GhZone $zone): RedirectResponse
    {
        $this->authorize('update', $zone);
        $this->zones->update($zone, $request->validated());

        return redirect()->route('gh.zones.index')->with('success', __('gh.flash.zone_updated'));
    }

    public function destroy(GhZone $zone): RedirectResponse
    {
        $this->authorize('delete', $zone);
        $this->zones->delete($zone);

        return redirect()->route('gh.zones.index')->with('success', __('gh.flash.zone_deleted'));
    }
}
