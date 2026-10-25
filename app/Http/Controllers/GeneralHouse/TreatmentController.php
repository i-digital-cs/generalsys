<?php

namespace App\Http\Controllers\GeneralHouse;

use App\Http\Controllers\Controller;
use App\Http\Requests\GeneralHouse\StoreGhTreatmentRequest;
use App\Http\Requests\GeneralHouse\UpdateGhTreatmentRequest;
use App\Models\GeneralHouse\GhTreatment;
use App\Services\GeneralHouse\Catalog\GhTreatmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TreatmentController extends Controller
{
    public function __construct(private readonly GhTreatmentService $treatments) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', GhTreatment::class);

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => (string) $request->query('status', ''),
        ];

        return view('gh.treatments.index', [
            'rows' => $this->treatments->paginate($filters),
            'filters' => $filters,
        ]);
    }

    public function store(StoreGhTreatmentRequest $request): RedirectResponse
    {
        $this->treatments->create($request->validated());

        return redirect()
            ->route('gh.treatments.index')
            ->with('success', __('gh.flash.treatment_created'));
    }

    public function update(UpdateGhTreatmentRequest $request, GhTreatment $treatment): RedirectResponse
    {
        $this->authorize('update', $treatment);
        $this->treatments->update($treatment, $request->validated());

        return redirect()
            ->route('gh.treatments.index', array_filter([
                'q' => $request->query('q'),
                'status' => $request->query('status'),
            ]))
            ->with('success', __('gh.flash.treatment_updated'));
    }

    public function destroy(GhTreatment $treatment): RedirectResponse
    {
        $this->authorize('delete', $treatment);
        $this->treatments->delete($treatment);

        return redirect()
            ->route('gh.treatments.index')
            ->with('success', __('gh.flash.treatment_deleted'));
    }
}
