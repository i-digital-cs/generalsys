<?php

namespace App\Http\Controllers\GeneralHouse;

use App\Http\Controllers\Controller;
use App\Http\Requests\GeneralHouse\StoreGhContractOfferingRequest;
use App\Http\Requests\GeneralHouse\StoreGhContractRequest;
use App\Http\Requests\GeneralHouse\UpdateGhContractOfferingRequest;
use App\Http\Requests\GeneralHouse\UpdateGhContractRequest;
use App\Models\GeneralHouse\GhB2bContract;
use App\Models\GeneralHouse\GhB2bContractOffering;
use App\Models\GeneralHouse\GhCustomer;
use App\Models\GeneralHouse\GhProductOffering;
use App\Services\GeneralHouse\Customers\GhContractService;
use App\Support\GeneralHouse\GhCatalogStatus;
use App\Support\GeneralHouse\GhCustomerType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContractController extends Controller
{
    public function __construct(private readonly GhContractService $contracts) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', GhB2bContract::class);
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => (string) $request->query('status', ''),
            'customer_id' => $request->query('customer_id', ''),
        ];

        return view('gh.contracts.index', [
            'rows' => $this->contracts->paginate($filters),
            'customers' => GhCustomer::query()->where('type', GhCustomerType::B2B)->orderBy('name_ar')->get(),
            'filters' => $filters,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', GhB2bContract::class);

        return view('gh.contracts.form', [
            'contract' => null,
            'customers' => GhCustomer::query()->where('type', GhCustomerType::B2B)->orderBy('name_ar')->get(),
            'selectedCustomerId' => $request->query('customer_id'),
        ]);
    }

    public function store(StoreGhContractRequest $request): RedirectResponse
    {
        $contract = $this->contracts->create($request->validated());

        return redirect()
            ->route('gh.contracts.show', $contract)
            ->with('success', __('gh.flash.contract_created'));
    }

    public function show(GhB2bContract $contract): View
    {
        $this->authorize('view', $contract);
        $contract->load(['customer', 'contractOfferings.offering.product', 'contractOfferings.offering.treatment']);

        return view('gh.contracts.show', [
            'contract' => $contract,
            'offerings' => GhProductOffering::query()
                ->with(['product', 'treatment'])
                ->where('status', GhCatalogStatus::ACTIVE)
                ->orderBy('id')
                ->get(),
        ]);
    }

    public function edit(GhB2bContract $contract): View
    {
        $this->authorize('update', $contract);

        return view('gh.contracts.form', [
            'contract' => $contract,
            'customers' => GhCustomer::query()->where('type', GhCustomerType::B2B)->orderBy('name_ar')->get(),
            'selectedCustomerId' => $contract->customer_id,
        ]);
    }

    public function update(UpdateGhContractRequest $request, GhB2bContract $contract): RedirectResponse
    {
        $this->authorize('update', $contract);
        $this->contracts->update($contract, $request->validated());

        return redirect()
            ->route('gh.contracts.show', $contract)
            ->with('success', __('gh.flash.contract_updated'));
    }

    public function destroy(GhB2bContract $contract): RedirectResponse
    {
        $this->authorize('delete', $contract);
        $this->contracts->delete($contract);

        return redirect()->route('gh.contracts.index')->with('success', __('gh.flash.contract_deleted'));
    }

    public function storeOffering(StoreGhContractOfferingRequest $request, GhB2bContract $contract): RedirectResponse
    {
        $this->authorize('update', $contract);
        $this->contracts->addOffering($contract, $request->validated());

        return redirect()
            ->route('gh.contracts.show', $contract)
            ->with('success', __('gh.flash.contract_offering_added'));
    }

    public function updateOffering(
        UpdateGhContractOfferingRequest $request,
        GhB2bContract $contract,
        GhB2bContractOffering $contractOffering
    ): RedirectResponse {
        $this->authorize('update', $contract);
        abort_unless($contractOffering->contract_id === $contract->id, 404);
        $this->contracts->updateOffering($contractOffering, $request->validated());

        return redirect()
            ->route('gh.contracts.show', $contract)
            ->with('success', __('gh.flash.contract_offering_updated'));
    }

    public function destroyOffering(GhB2bContract $contract, GhB2bContractOffering $contractOffering): RedirectResponse
    {
        $this->authorize('update', $contract);
        abort_unless($contractOffering->contract_id === $contract->id, 404);
        $this->contracts->removeOffering($contractOffering);

        return redirect()
            ->route('gh.contracts.show', $contract)
            ->with('success', __('gh.flash.contract_offering_removed'));
    }
}
