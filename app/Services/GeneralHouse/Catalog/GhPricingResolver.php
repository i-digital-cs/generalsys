<?php

namespace App\Services\GeneralHouse\Catalog;

use App\Models\GeneralHouse\GhB2bContract;
use App\Models\GeneralHouse\GhB2bContractOffering;
use App\Models\GeneralHouse\GhCustomer;
use App\Models\GeneralHouse\GhProductOffering;
use App\Support\GeneralHouse\GhCatalogStatus;
use App\Support\GeneralHouse\GhContractStatus;
use App\Support\GeneralHouse\GhCustomerType;
use App\Support\GeneralHouse\GhMoney;
use App\Support\GeneralHouse\GhTaxClass;
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
                'unit_price' => GhMoney::of((string) $o->standard_price),
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
                'unit_price' => GhMoney::of((string) $row->contract_price),
                'currency' => $row->currency ?: 'AED',
                'source' => 'CONTRACT',
            ])
            ->values();
    }

    /**
     * Invoice builder product groups.
     * B2C: all active catalog products (standard prices).
     * B2B default: active contract offerings only.
     * B2B exception catalog: full sellable offerings marked out_of_contract.
     *
     * @return array{
     *   mode: 'B2C'|'B2B_CONTRACT'|'B2B_NO_CONTRACT',
     *   groups: list<array<string, mixed>>,
     *   ooc_groups: list<array<string, mixed>>,
     *   contract_reference: ?string,
     *   message: ?string
     * }
     */
    public function invoiceBuilderCatalog(GhCustomer $customer, Carbon|string|null $onDate = null): array
    {
        $day = $onDate === null ? Carbon::today() : Carbon::parse($onDate)->startOfDay();

        if ($customer->type === GhCustomerType::B2C) {
            return [
                'mode' => 'B2C',
                'groups' => $this->productGroupsFromOfferings($this->availableOfferingsForCustomer($customer, $day), includeEmptyProducts: true),
                'ooc_groups' => [],
                'contract_reference' => null,
                'message' => null,
            ];
        }

        $contract = $this->activeContractFor($customer, $day);
        if ($contract === null) {
            return [
                'mode' => 'B2B_NO_CONTRACT',
                'groups' => [],
                'ooc_groups' => [],
                'contract_reference' => null,
                'message' => __('gh.invoices.b2b_no_contract_catalog'),
            ];
        }

        $contractOfferings = $this->availableOfferingsForCustomer($customer, $day);
        $contractIds = $contractOfferings->map(fn ($r) => (int) $r['offering']->id)->all();
        $ooc = $this->sellable->activeOfferings()
            ->reject(fn (GhProductOffering $o) => in_array((int) $o->id, $contractIds, true))
            ->map(fn (GhProductOffering $o) => [
                'offering' => $o,
                'unit_price' => '0.00',
                'currency' => $o->currency ?: 'AED',
                'source' => 'STAFF',
                'out_of_contract' => true,
            ]);

        return [
            'mode' => 'B2B_CONTRACT',
            'groups' => $this->productGroupsFromOfferings($contractOfferings, includeEmptyProducts: false),
            'ooc_groups' => $this->productGroupsFromOfferings($ooc, includeEmptyProducts: false, outOfContract: true),
            'contract_reference' => $contract->reference,
            'message' => null,
        ];
    }

    /**
     * @param  Collection<int, array{offering: GhProductOffering, unit_price: string, currency?: string, source?: string, out_of_contract?: bool}>  $rows
     * @return list<array<string, mixed>>
     */
    private function productGroupsFromOfferings(Collection $rows, bool $includeEmptyProducts = false, bool $outOfContract = false): array
    {
        $groups = [];
        foreach ($rows as $row) {
            /** @var GhProductOffering $offering */
            $offering = $row['offering'];
            $product = $offering->product;
            if ($product === null) {
                continue;
            }
            $groups[$product->id] ??= [
                'product_id' => (int) $product->id,
                'code' => (string) ($product->code ?? ''),
                'name_ar' => (string) $product->name_ar,
                'name_en' => (string) ($product->name_en ?: $product->name_ar),
                'category_id' => $product->category_id,
                'image_url' => $product->imageUrl(),
                'out_of_contract' => $outOfContract || (bool) ($row['out_of_contract'] ?? false),
                'treatments' => [],
            ];
            $groups[$product->id]['treatments'][] = [
                'offering_id' => (int) $offering->id,
                'name_ar' => (string) $offering->treatment?->name_ar,
                'name_en' => (string) ($offering->treatment?->name_en ?: $offering->treatment?->name_ar),
                'unit_price' => (string) $row['unit_price'],
                'source' => (string) ($row['source'] ?? 'STANDARD'),
                'tax_class' => (string) ($offering->tax_class ?: GhTaxClass::STANDARD_RATED),
            ];
        }

        if ($includeEmptyProducts) {
            foreach ($this->sellable->activeProductsForCounting() as $product) {
                if (isset($groups[$product->id])) {
                    continue;
                }
                $groups[$product->id] = [
                    'product_id' => (int) $product->id,
                    'code' => (string) ($product->code ?? ''),
                    'name_ar' => (string) $product->name_ar,
                    'name_en' => (string) ($product->name_en ?: $product->name_ar),
                    'category_id' => $product->category_id,
                    'image_url' => $product->imageUrl(),
                    'out_of_contract' => false,
                    'treatments' => [],
                    'setup_warning' => true,
                ];
            }
        }

        return array_values($groups);
    }

    /**
     * Full sellable catalog for driver pickup counting.
     * Physical receipt must not depend on B2B contract offering rows — pricing is finalized at invoice.
     *
     * @return Collection<int, array{offering: GhProductOffering, unit_price: string, currency: string, source: 'STANDARD'}>
     */
    public function availablePickupOfferings(): Collection
    {
        return $this->sellable->activeOfferings()->map(fn (GhProductOffering $o) => [
            'offering' => $o,
            'unit_price' => GhMoney::of((string) $o->standard_price),
            'currency' => $o->currency ?: 'AED',
            'source' => 'STANDARD',
        ]);
    }

    /**
     * Non-binding pickup snapshot. B2B out-of-contract garments are flagged for staff review
     * with zero price — never silently apply standard/contract rates.
     *
     * @return array{unit_price: string, currency: string, source: 'STANDARD'|'CONTRACT', pricing_review_required: bool}
     */
    public function provisionalPickupPrice(GhCustomer $customer, GhProductOffering $offering, Carbon|string|null $onDate = null): array
    {
        if (! $this->sellable->isSellable($offering)) {
            throw ValidationException::withMessages([
                'offering_id' => [__('gh.errors.offering_not_sellable')],
            ]);
        }

        try {
            $resolved = $this->resolvePrice($customer, $offering, $onDate);

            return $resolved + ['pricing_review_required' => false];
        } catch (ValidationException $e) {
            if ($customer->type !== GhCustomerType::B2B) {
                throw $e;
            }

            // Physical receipt allowed; invoicing requires explicit staff pricing.
            return [
                'unit_price' => GhMoney::of('0.00'),
                'currency' => $offering->currency ?: 'AED',
                'source' => 'STANDARD',
                'pricing_review_required' => true,
            ];
        }
    }

    public function isOnActiveContract(GhCustomer $customer, GhProductOffering $offering, Carbon|string|null $onDate = null): bool
    {
        if ($customer->type !== GhCustomerType::B2B) {
            return true;
        }

        try {
            $this->resolvePrice($customer, $offering, $onDate);

            return true;
        } catch (ValidationException) {
            return false;
        }
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
                'unit_price' => GhMoney::of((string) $offering->standard_price),
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
            'unit_price' => GhMoney::of((string) $row->contract_price),
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
