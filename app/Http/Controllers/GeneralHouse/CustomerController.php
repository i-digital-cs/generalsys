<?php

namespace App\Http\Controllers\GeneralHouse;

use App\Http\Controllers\Controller;
use App\Http\Requests\GeneralHouse\StoreGhCustomerRequest;
use App\Http\Requests\GeneralHouse\UpdateGhCustomerRequest;
use App\Models\GeneralHouse\GhCustomer;
use App\Services\GeneralHouse\Customers\GhCustomerService;
use App\Services\GeneralHouse\Customers\GhZoneService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function __construct(
        private readonly GhCustomerService $customers,
        private readonly GhZoneService $zones,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', GhCustomer::class);
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => (string) $request->query('status', ''),
            'type' => (string) $request->query('type', ''),
        ];

        return view('gh.customers.index', [
            'rows' => $this->customers->paginate($filters),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', GhCustomer::class);

        return view('gh.customers.form', ['customer' => null]);
    }

    public function lookup(Request $request): JsonResponse
    {
        $this->authorize('create', GhCustomer::class);

        $exceptId = $request->filled('except_id') ? (int) $request->query('except_id') : null;

        return response()->json($this->customers->lookupDuplicates(
            $request->query('phone'),
            $request->query('name_ar'),
            $request->query('name_en'),
            $exceptId > 0 ? $exceptId : null,
        ));
    }

    public function store(StoreGhCustomerRequest $request): RedirectResponse
    {
        $customer = $this->customers->create($request->validated());

        return redirect()
            ->route('gh.customers.show', $customer)
            ->with('success', __('gh.flash.customer_created'));
    }

    public function show(GhCustomer $customer): View
    {
        $this->authorize('view', $customer);
        $customer->load(['addresses.zone', 'contracts' => fn ($q) => $q->orderByDesc('effective_from')]);

        return view('gh.customers.show', [
            'customer' => $customer,
            'zones' => $this->zones->allOrdered(),
        ]);
    }

    public function edit(GhCustomer $customer): View
    {
        $this->authorize('update', $customer);

        return view('gh.customers.form', ['customer' => $customer]);
    }

    public function update(UpdateGhCustomerRequest $request, GhCustomer $customer): RedirectResponse
    {
        $this->authorize('update', $customer);
        $this->customers->update($customer, $request->validated());

        return redirect()
            ->route('gh.customers.show', $customer)
            ->with('success', __('gh.flash.customer_updated'));
    }

    public function destroy(GhCustomer $customer): RedirectResponse
    {
        $this->authorize('delete', $customer);
        $this->customers->delete($customer);

        return redirect()->route('gh.customers.index')->with('success', __('gh.flash.customer_deleted'));
    }
}
