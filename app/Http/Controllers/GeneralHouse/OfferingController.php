<?php

namespace App\Http\Controllers\GeneralHouse;

use App\Http\Controllers\Controller;
use App\Http\Requests\GeneralHouse\StoreGhOfferingRequest;
use App\Http\Requests\GeneralHouse\UpdateGhOfferingRequest;
use App\Models\GeneralHouse\GhProduct;
use App\Models\GeneralHouse\GhProductOffering;
use App\Models\GeneralHouse\GhTreatment;
use App\Services\GeneralHouse\Catalog\GhOfferingService;
use App\Support\GeneralHouse\GhTaxClass;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OfferingController extends Controller
{
    public function __construct(private readonly GhOfferingService $offerings) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', GhProductOffering::class);

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => (string) $request->query('status', ''),
            'product_id' => $request->query('product_id', ''),
            'treatment_id' => $request->query('treatment_id', ''),
        ];

        return view('gh.offerings.index', [
            'rows' => $this->offerings->paginate($filters),
            'products' => GhProduct::query()->orderBy('name_en')->get(),
            'treatments' => GhTreatment::query()->orderBy('sort_order')->orderBy('name_en')->get(),
            'taxClasses' => GhTaxClass::ALL,
            'filters' => $filters,
        ]);
    }

    public function store(StoreGhOfferingRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['currency'] = $data['currency'] ?? 'AED';
        $this->offerings->create($data);

        return redirect()
            ->route('gh.offerings.index')
            ->with('success', __('gh.flash.offering_created'));
    }

    public function update(UpdateGhOfferingRequest $request, GhProductOffering $offering): RedirectResponse
    {
        $this->authorize('update', $offering);
        $data = $request->validated();
        $data['currency'] = $data['currency'] ?? 'AED';
        $this->offerings->update($offering, $data);

        return redirect()
            ->route('gh.offerings.index', array_filter([
                'q' => $request->query('q'),
                'status' => $request->query('status'),
                'product_id' => $request->query('product_id'),
                'treatment_id' => $request->query('treatment_id'),
            ]))
            ->with('success', __('gh.flash.offering_updated'));
    }

    public function destroy(GhProductOffering $offering): RedirectResponse
    {
        $this->authorize('delete', $offering);
        $this->offerings->delete($offering);

        return redirect()
            ->route('gh.offerings.index')
            ->with('success', __('gh.flash.offering_deleted'));
    }
}
