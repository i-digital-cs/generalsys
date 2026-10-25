<?php

namespace App\Http\Controllers\GeneralHouse;

use App\Http\Controllers\Controller;
use App\Http\Requests\GeneralHouse\StoreGhCustomerAddressRequest;
use App\Http\Requests\GeneralHouse\UpdateGhCustomerAddressRequest;
use App\Models\GeneralHouse\GhCustomer;
use App\Models\GeneralHouse\GhCustomerAddress;
use App\Services\GeneralHouse\Customers\GhCustomerAddressService;
use Illuminate\Http\RedirectResponse;

class CustomerAddressController extends Controller
{
    public function __construct(private readonly GhCustomerAddressService $addresses) {}

    public function store(StoreGhCustomerAddressRequest $request, GhCustomer $customer): RedirectResponse
    {
        $this->authorize('update', $customer);
        $this->addresses->create($customer, $request->validated());

        return redirect()
            ->route('gh.customers.show', $customer)
            ->with('success', __('gh.flash.address_created'));
    }

    public function update(UpdateGhCustomerAddressRequest $request, GhCustomer $customer, GhCustomerAddress $address): RedirectResponse
    {
        $this->authorize('update', $customer);
        abort_unless($address->customer_id === $customer->id, 404);
        $this->addresses->update($address, $request->validated());

        return redirect()
            ->route('gh.customers.show', $customer)
            ->with('success', __('gh.flash.address_updated'));
    }

    public function destroy(GhCustomer $customer, GhCustomerAddress $address): RedirectResponse
    {
        $this->authorize('update', $customer);
        abort_unless($address->customer_id === $customer->id, 404);
        $this->addresses->delete($address);

        return redirect()
            ->route('gh.customers.show', $customer)
            ->with('success', __('gh.flash.address_deleted'));
    }

    public function makeDefault(GhCustomer $customer, GhCustomerAddress $address): RedirectResponse
    {
        $this->authorize('update', $customer);
        abort_unless($address->customer_id === $customer->id, 404);
        $this->addresses->makeDefault($address);

        return redirect()
            ->route('gh.customers.show', $customer)
            ->with('success', __('gh.flash.address_default_set'));
    }
}
