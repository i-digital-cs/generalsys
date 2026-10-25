<?php

namespace App\Services\GeneralHouse\Catalog;

use App\Models\GeneralHouse\GhB2bContract;
use App\Models\GeneralHouse\GhB2bContractOffering;
use App\Models\GeneralHouse\GhCustomer;
use App\Models\GeneralHouse\GhProductOffering;
use App\Support\GeneralHouse\GhCatalogStatus;
use App\Support\GeneralHouse\GhContractStatus;
use App\Support\GeneralHouse\GhCustomerType;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Phase 2 price/catalog resolution for a customer on a given date.
 * Does not implement invoice VAT or min-order surcharge math.
 */
class GhPricingResolver
{
    public function __construct(
        private readonly GhSellableCatalogService $sellable,
    ) {}

    /**
     * Offerings available to the customer with resolved unit prices.
     *
     * @return Collection<int, array{offering: GhProductOffering, unit_price: string, currency: string, source: 'STANDARD'|'CONTRACT'}>
     */
    public function availableOfferingsForCustomer(GhCustomer $customer, Carbon|string|null $onDate = null): Collection
    {
        $day = $onDate === null ? Carbon::today() : Carbon::parse($onDate)->startOfDay();

        if ($customer->type === GhCustomerType::B2C) {
            return $this->sellable->activeOfferings()->map(fn (GhProductOffering $o) => [
                'offering' => $o,
                'unit_price' => number_format((float) $o->standard_price, 2, '.', ''),
                'currency' => $o->currency ?: 'AED',
                'source' => 'STANDARD',
            ]);
        }

        $contract = $this->activeContractFor($customer, $day);
        if ($contract === null) {
            return collect();
        }

        return $contract->contractOfferings()
            ->with(['offering.product.category', 'offering.treatment'])
            ->where('status', GhCatalogStatus::ACTIVE)
            ->get()
            ->filter(function (GhB2bContractOffering $row) {
                $offering = $row->offering;
                if ($offering === null) {
                    return false;
                }

                return $this->sellable->isSellable($offering);
            })
            ->map(fn (GhB2bContractOffering $row) => [
                'offering' => $row->offering,
                'unit_price' => number_format((float) $row->contract_price, 2, '.', ''),
                'currency' => $row->currency ?: 'AED',
                'source' => 'CONTRACT',
            ])
            ->values();
    }

    /**
     * @return array{unit_price: string, currency: string, source: 'STANDARD'|'CONTRACT'}
     */
    public function resolvePrice(GhCustomer $customer, GhProductOffering $offering, Carbon|string|null $onDate = null): array
    {
        $day = $onDate === null ? Carbon::today() : Carbon::parse($onDate)->startOfDay();

        if (! $this->sellable->isSellable($offering)) {
            throw ValidationException::withMessages([
                'offering_id' => [__('gh.errors.offering_not_sellable')],
            ]);
        }

        if ($customer->type === GhCustomerType::B2C) {
            return [
                'unit_price' => number_format((float) $offering->standard_price, 2, '.', ''),
                'currency' => $offering->currency ?: 'AED',
                'source' => 'STANDARD',
            ];
        }

        $contract = $this->activeContractFor($customer, $day);
        if ($contract === null) {
            throw ValidationException::withMessages([
                'customer_id' => [__('gh.errors.b2b_no_active_contract')],
            ]);
        }

        $row = GhB2bContractOffering::query()
            ->where('contract_id', $contract->id)
            ->where('offering_id', $offering->id)
            ->where('status', GhCatalogStatus::ACTIVE)
            ->first();

        if ($row === null) {
            throw ValidationException::withMessages([
                'offering_id' => [__('gh.errors.offering_not_on_contract')],
            ]);
        }

        return [
            'unit_price' => number_format((float) $row->contract_price, 2, '.', ''),
            'currency' => $row->currency ?: 'AED',
            'source' => 'CONTRACT',
        ];
    }

    public function activeContractFor(GhCustomer $customer, Carbon|string|null $onDate = null): ?GhB2bContract
    {
        $day = $onDate === null ? Carbon::today() : Carbon::parse($onDate)->startOfDay();

        return GhB2bContract::query()
            ->where('customer_id', $customer->id)
            ->where('status', GhContractStatus::ACTIVE)
            ->whereDate('effective_from', '<=', $day->toDateString())
            ->where(function ($q) use ($day) {
                $q->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $day->toDateString());
            })
            ->orderByDesc('effective_from')
            ->first();
    }
}
