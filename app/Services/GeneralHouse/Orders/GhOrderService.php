<?php

namespace App\Services\GeneralHouse\Orders;

use App\Models\GeneralHouse\GhCustomer;
use App\Models\GeneralHouse\GhCustomerAddress;
use App\Models\GeneralHouse\GhOrder;
use App\Models\GeneralHouse\GhOrderLine;
use App\Models\GeneralHouse\GhProductOffering;
use App\Models\User;
use App\Services\GeneralHouse\Catalog\GhPricingResolver;
use App\Services\GeneralHouse\Customers\GhCustomerAddressService;
use App\Services\GeneralHouse\Customers\GhCustomerService;
use App\Support\GeneralHouse\GhCatalogStatus;
use App\Support\GeneralHouse\GhCustomerType;
use App\Support\GeneralHouse\GhMoney;
use App\Support\GeneralHouse\GhOrderEventType;
use App\Support\GeneralHouse\GhOrderStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GhOrderService
{
    public function __construct(
        private readonly GhOrderReferenceService $references,
        private readonly GhPickupEligibilityService $eligibility,
        private readonly GhPricingResolver $pricing,
        private readonly GhCustomerService $customers,
        private readonly GhCustomerAddressService $addresses,
        private readonly GhOrderAssignmentService $assignments,
        private readonly GhOrderEventService $events,
        private readonly GhDriverEligibilityService $drivers,
        private readonly GhOrderStateService $states,
    ) {}

    /**
     * @param  array{
     *   q?: string,
     *   reference?: string,
     *   customer?: string,
     *   status?: string,
     *   zone_id?: int|string,
     *   pickup_driver_id?: int|string,
     *   pickup_from?: string,
     *   pickup_to?: string
     * }  $filters
     * @return LengthAwarePaginator<int, GhOrder>
     */
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $perPage = max(1, min(50, $perPage));
        $q = GhOrder::query()
            ->with(['customer', 'zone', 'pickupDriver', 'deliveryDriver', 'address'])
            ->orderByDesc('pickup_starts_at')
            ->orderByDesc('id');

        $refOnly = trim((string) ($filters['reference'] ?? ''));
        if ($refOnly !== '') {
            $q->where('reference', 'like', '%'.$refOnly.'%');
        }

        $customer = trim((string) ($filters['customer'] ?? ''));
        if ($customer !== '') {
            $like = '%'.$customer.'%';
            $q->whereHas('customer', function ($c) use ($like) {
                $c->where('name_ar', 'like', $like)
                    ->orWhere('name_en', 'like', $like)
                    ->orWhere('phone', 'like', $like);
            });
        }

        $qCombined = trim((string) ($filters['q'] ?? ''));
        if ($qCombined !== '' && $refOnly === '' && $customer === '') {
            $like = '%'.$qCombined.'%';
            $q->where(function ($inner) use ($like) {
                $inner->where('reference', 'like', $like)
                    ->orWhereHas('customer', function ($c) use ($like) {
                        $c->where('name_ar', 'like', $like)
                            ->orWhere('name_en', 'like', $like)
                            ->orWhere('phone', 'like', $like);
                    });
            });
        }

        $status = (string) ($filters['status'] ?? '');
        if (in_array($status, GhOrderStatus::ALL, true)) {
            $q->where('status', $status);
        }

        $zoneId = (int) ($filters['zone_id'] ?? 0);
        if ($zoneId > 0) {
            $q->where('zone_id', $zoneId);
        }

        $pickupDriverId = (int) ($filters['pickup_driver_id'] ?? 0);
        if ($pickupDriverId > 0) {
            $q->where('pickup_driver_id', $pickupDriverId);
        }

        $from = trim((string) ($filters['pickup_from'] ?? ''));
        if ($from !== '') {
            $q->where(
                'pickup_starts_at',
                '>=',
                $this->eligibility->toUtcSql(Carbon::parse($from, $this->eligibility->timezone())->startOfDay())
            );
        }

        $to = trim((string) ($filters['pickup_to'] ?? ''));
        if ($to !== '') {
            $q->where(
                'pickup_starts_at',
                '<=',
                $this->eligibility->toUtcSql(Carbon::parse($to, $this->eligibility->timezone())->endOfDay())
            );
        }

        return $q->paginate($perPage)->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): GhOrder
    {
        return DB::transaction(function () use ($data, $actor) {
            $customer = $this->resolveCustomer($data);
            $address = $this->resolveAddress($customer, $data);
            $window = $this->parsePickupWindow($data);
            $linesInput = $this->normalizeLines($data['lines'] ?? []);

            $pickupDriverId = (int) ($data['pickup_driver_id'] ?? 0);
            $this->drivers->assertEligible($pickupDriverId, 'pickup_driver_id');
            $deliveryDriverId = isset($data['delivery_driver_id']) && $data['delivery_driver_id'] !== '' && $data['delivery_driver_id'] !== null
                ? (int) $data['delivery_driver_id']
                : null;
            $this->drivers->assertOptionalEligible($deliveryDriverId, 'delivery_driver_id');

            $contract = $customer->type === GhCustomerType::B2B
                ? $this->pricing->activeContractFor($customer, $window['starts_at'])
                : null;

            if ($customer->type === GhCustomerType::B2B && $contract === null) {
                throw ValidationException::withMessages([
                    'customer_id' => [__('gh.errors.b2b_no_active_contract')],
                ]);
            }

            $builtLines = $this->buildLines($customer, $linesInput, $window['starts_at'], $contract?->id);
            $estimated = $this->sumEstimates($builtLines);
            $status = $this->eligibility->initialStatus($window['starts_at']);
            $reference = $this->references->nextReference();

            $order = GhOrder::query()->forceCreate([
                'reference' => $reference,
                'customer_id' => $customer->id,
                'address_id' => $address->id,
                'zone_id' => $address->zone_id,
                'contract_id' => $contract?->id,
                'status' => $status,
                'pickup_starts_at' => $window['starts_at'],
                'pickup_ends_at' => $window['ends_at'],
                'timezone' => $window['timezone'],
                'notes' => $data['notes'] ?? null,
                'scheduling_notes' => $data['scheduling_notes'] ?? null,
                'pickup_driver_id' => null,
                'delivery_driver_id' => null,
                'estimated_subtotal' => $estimated,
                'currency' => 'AED',
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            foreach ($builtLines as $i => $line) {
                GhOrderLine::query()->create(array_merge($line, [
                    'order_id' => $order->id,
                    'sort_order' => $i,
                ]));
            }

            $this->events->record(
                $order,
                GhOrderEventType::ORDER_CREATED,
                $actor,
                null,
                $status,
                [
                    'reference' => $reference,
                    'estimated_subtotal' => $estimated,
                    'line_count' => count($builtLines),
                    'pricing_note' => 'estimate_only',
                ],
            );

            $this->assignments->assignPickup($order, $pickupDriverId, $actor);
            if ($deliveryDriverId !== null) {
                $this->assignments->assignDelivery($order, $deliveryDriverId, $actor);
            }

            return $order->fresh(['customer', 'address', 'zone', 'lines', 'pickupDriver', 'deliveryDriver', 'events']);
        });
    }

    /**
     * Full operational update before physical pickup / invoice.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(GhOrder $order, array $data, User $actor): GhOrder
    {
        $this->assertEditable($order);

        return DB::transaction(function () use ($order, $data, $actor) {
            $order = GhOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $this->assertEditable($order);

            $customer = $order->customer()->firstOrFail();
            $address = $this->resolveAddressForUpdate($order, $customer, $data);
            $window = $this->parsePickupWindow($data);
            $linesInput = $this->normalizeLines($data['lines'] ?? []);

            $pickupDriverId = (int) ($data['pickup_driver_id'] ?? 0);
            $this->drivers->assertEligible($pickupDriverId, 'pickup_driver_id');
            $deliveryDriverId = isset($data['delivery_driver_id']) && $data['delivery_driver_id'] !== '' && $data['delivery_driver_id'] !== null
                ? (int) $data['delivery_driver_id']
                : null;
            $this->drivers->assertOptionalEligible($deliveryDriverId, 'delivery_driver_id');

            $contract = $customer->type === GhCustomerType::B2B
                ? $this->pricing->activeContractFor($customer, $window['starts_at'])
                : null;

            if ($customer->type === GhCustomerType::B2B && $contract === null) {
                throw ValidationException::withMessages([
                    'customer_id' => [__('gh.errors.b2b_no_active_contract')],
                ]);
            }

            $builtLines = $this->buildLines($customer, $linesInput, $window['starts_at'], $contract?->id);
            $estimated = $this->sumEstimates($builtLines);
            $windowChanged = ! $order->pickup_starts_at->equalTo($window['starts_at'])
                || ! $order->pickup_ends_at->equalTo($window['ends_at']);

            // Status is never written here — reevaluatePrePickupStatus owns LATER/PICKUP flips.
            $order->forceFill([
                'address_id' => $address->id,
                'zone_id' => $address->zone_id,
                'contract_id' => $contract?->id,
                'pickup_starts_at' => $window['starts_at'],
                'pickup_ends_at' => $window['ends_at'],
                'timezone' => $window['timezone'],
                'notes' => $data['notes'] ?? null,
                'scheduling_notes' => $data['scheduling_notes'] ?? null,
                'estimated_subtotal' => $estimated,
                'updated_by' => $actor->id,
            ])->save();

            $order->lines()->delete();
            foreach ($builtLines as $i => $line) {
                GhOrderLine::query()->create(array_merge($line, [
                    'order_id' => $order->id,
                    'sort_order' => $i,
                ]));
            }

            $this->events->record($order, GhOrderEventType::ORDER_UPDATED, $actor, null, null, [
                'fields' => ['address', 'window', 'lines', 'notes', 'drivers'],
            ]);
            $this->events->record($order, GhOrderEventType::LINES_REPLACED, $actor, null, null, [
                'line_count' => count($builtLines),
                'estimated_subtotal' => $estimated,
            ]);

            if ($windowChanged) {
                $this->events->record($order, GhOrderEventType::PICKUP_WINDOW_CHANGED, $actor, null, null, [
                    'pickup_starts_at' => $window['starts_at']->toIso8601String(),
                    'pickup_ends_at' => $window['ends_at']->toIso8601String(),
                ]);
            }

            $this->states->reevaluatePrePickupStatus($order->fresh(), $actor);
            $this->assignments->assignPickup($order, $pickupDriverId, $actor);
            $this->assignments->assignDelivery($order, $deliveryDriverId, $actor);

            return $order->fresh(['customer', 'address', 'zone', 'lines', 'pickupDriver', 'deliveryDriver', 'events']);
        });
    }

    /**
     * Restricted post-pickup update: notes + delivery driver only.
     *
     * @param  array{notes?: ?string, scheduling_notes?: ?string, delivery_driver_id?: int|string|null}  $data
     */
    public function updateRestricted(GhOrder $order, array $data, User $actor): GhOrder
    {
        if ($order->invoice_issued_at !== null) {
            throw ValidationException::withMessages([
                'order' => [__('gh.errors.order_locked_invoiced')],
            ]);
        }

        if ($order->isEditableOperationally()) {
            throw ValidationException::withMessages([
                'order' => [__('gh.errors.order_use_full_edit')],
            ]);
        }

        return DB::transaction(function () use ($order, $data, $actor) {
            $order = GhOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            $order->forceFill([
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $order->notes,
                'scheduling_notes' => array_key_exists('scheduling_notes', $data)
                    ? $data['scheduling_notes']
                    : $order->scheduling_notes,
                'updated_by' => $actor->id,
            ])->save();

            if (array_key_exists('delivery_driver_id', $data)) {
                $deliveryDriverId = $data['delivery_driver_id'] !== '' && $data['delivery_driver_id'] !== null
                    ? (int) $data['delivery_driver_id']
                    : null;
                $this->drivers->assertOptionalEligible($deliveryDriverId, 'delivery_driver_id');
                $this->assignments->assignDelivery($order, $deliveryDriverId, $actor);
            }

            $this->events->record($order, GhOrderEventType::ORDER_UPDATED, $actor, null, null, [
                'fields' => ['notes', 'delivery_driver'],
                'mode' => 'restricted_post_pickup',
            ]);

            return $order->fresh(['customer', 'address', 'zone', 'lines', 'pickupDriver', 'deliveryDriver', 'events']);
        });
    }

    public function assertEditable(GhOrder $order): void
    {
        if ($order->invoice_issued_at !== null) {
            throw ValidationException::withMessages([
                'order' => [__('gh.errors.order_locked_invoiced')],
            ]);
        }

        if ($order->picked_up_at !== null || ! GhOrderStatus::isPrePickup($order->status)) {
            throw ValidationException::withMessages([
                'order' => [__('gh.errors.order_locked_after_pickup')],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{starts_at: Carbon, ends_at: Carbon, timezone: string}
     */
    public function parsePickupWindow(array $data): array
    {
        $tz = $this->eligibility->timezone();
        $date = trim((string) ($data['pickup_date'] ?? ''));
        $start = trim((string) ($data['pickup_window_start'] ?? ''));
        $end = trim((string) ($data['pickup_window_end'] ?? ''));

        if ($date === '' || $start === '' || $end === '') {
            throw ValidationException::withMessages([
                'pickup_date' => [__('gh.errors.pickup_window_required')],
            ]);
        }

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw ValidationException::withMessages([
                'pickup_date' => [__('gh.errors.pickup_date_invalid')],
            ]);
        }

        if (! preg_match('/^\d{2}:\d{2}$/', $start) || ! preg_match('/^\d{2}:\d{2}$/', $end)) {
            throw ValidationException::withMessages([
                'pickup_window_start' => [__('gh.errors.pickup_time_invalid')],
            ]);
        }

        try {
            $startsAt = Carbon::createFromFormat('Y-m-d H:i', $date.' '.$start, $tz);
            $endsAt = Carbon::createFromFormat('Y-m-d H:i', $date.' '.$end, $tz);
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'pickup_date' => [__('gh.errors.pickup_window_ambiguous')],
            ]);
        }

        if ($startsAt === false || $endsAt === false) {
            throw ValidationException::withMessages([
                'pickup_date' => [__('gh.errors.pickup_window_ambiguous')],
            ]);
        }

        // Reject ambiguous formats (createFromFormat can be loose without strict)
        if ($startsAt->format('Y-m-d H:i') !== $date.' '.$start
            || $endsAt->format('Y-m-d H:i') !== $date.' '.$end) {
            throw ValidationException::withMessages([
                'pickup_date' => [__('gh.errors.pickup_window_ambiguous')],
            ]);
        }

        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            throw ValidationException::withMessages([
                'pickup_window_end' => [__('gh.errors.pickup_window_end_before_start')],
            ]);
        }

        return [
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'timezone' => $tz,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveCustomer(array $data): GhCustomer
    {
        if (! empty($data['customer_id'])) {
            $customer = GhCustomer::query()->find((int) $data['customer_id']);
            if ($customer === null) {
                throw ValidationException::withMessages([
                    'customer_id' => [__('gh.errors.customer_missing')],
                ]);
            }
            if ($customer->status !== GhCatalogStatus::ACTIVE) {
                throw ValidationException::withMessages([
                    'customer_id' => [__('gh.errors.customer_inactive')],
                ]);
            }

            return $customer;
        }

        $new = $data['new_customer'] ?? null;
        if (! is_array($new)) {
            throw ValidationException::withMessages([
                'customer_id' => [__('gh.errors.customer_required')],
            ]);
        }

        $type = $new['type'] ?? GhCustomerType::B2C;
        if ($type === GhCustomerType::B2B) {
            throw ValidationException::withMessages([
                'new_customer.type' => [__('gh.errors.order_new_b2b_needs_contract')],
            ]);
        }

        return $this->customers->create([
            'type' => $type,
            'name_ar' => $new['name_ar'] ?? '',
            'name_en' => $new['name_en'] ?? null,
            'phone' => $new['phone'] ?? null,
            'email' => $new['email'] ?? null,
            'billing_name' => $new['billing_name'] ?? null,
            'billing_address' => $new['billing_address'] ?? null,
            'tax_registration_number' => $new['tax_registration_number'] ?? null,
            'status' => GhCatalogStatus::ACTIVE,
            'notes' => $new['notes'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveAddress(GhCustomer $customer, array $data): GhCustomerAddress
    {
        if (! empty($data['address_id'])) {
            $address = GhCustomerAddress::query()->find((int) $data['address_id']);
            if ($address === null || (int) $address->customer_id !== (int) $customer->id) {
                throw ValidationException::withMessages([
                    'address_id' => [__('gh.errors.address_not_owned')],
                ]);
            }

            return $address;
        }

        $new = $data['new_address'] ?? null;
        if (! is_array($new)) {
            throw ValidationException::withMessages([
                'address_id' => [__('gh.errors.address_required')],
            ]);
        }

        return $this->addresses->create($customer, $new);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveAddressForUpdate(GhOrder $order, GhCustomer $customer, array $data): GhCustomerAddress
    {
        if (! empty($data['address_id'])) {
            $address = GhCustomerAddress::query()->find((int) $data['address_id']);
            if ($address === null || (int) $address->customer_id !== (int) $customer->id) {
                throw ValidationException::withMessages([
                    'address_id' => [__('gh.errors.address_not_owned')],
                ]);
            }

            return $address;
        }

        if (! empty($data['new_address']) && is_array($data['new_address'])) {
            return $this->addresses->create($customer, $data['new_address']);
        }

        return $order->address()->firstOrFail();
    }

    /**
     * @return list<array{offering_id: int, quantity: int}>
     */
    private function normalizeLines(mixed $lines): array
    {
        if (! is_array($lines) || $lines === []) {
            throw ValidationException::withMessages([
                'lines' => [__('gh.errors.order_lines_required')],
            ]);
        }

        $normalized = [];
        foreach ($lines as $i => $row) {
            if (! is_array($row)) {
                continue;
            }
            $offeringId = (int) ($row['offering_id'] ?? 0);
            $qty = (int) ($row['quantity'] ?? 0);
            if ($offeringId < 1 || $qty < 1) {
                throw ValidationException::withMessages([
                    "lines.$i.quantity" => [__('gh.errors.order_line_invalid')],
                ]);
            }
            // Keep same product×different treatments as separate lines — never merge by product.
            $normalized[] = [
                'offering_id' => $offeringId,
                'quantity' => $qty,
            ];
        }

        if ($normalized === []) {
            throw ValidationException::withMessages([
                'lines' => [__('gh.errors.order_lines_required')],
            ]);
        }

        return $normalized;
    }

    /**
     * @param  list<array{offering_id: int, quantity: int}>  $lines
     * @return list<array<string, mixed>>
     */
    private function buildLines(GhCustomer $customer, array $lines, Carbon $onDate, ?int $contractId): array
    {
        $built = [];
        foreach ($lines as $i => $row) {
            $offering = GhProductOffering::query()
                ->with(['product.category', 'treatment'])
                ->find($row['offering_id']);

            if ($offering === null) {
                throw ValidationException::withMessages([
                    "lines.$i.offering_id" => [__('gh.errors.offering_missing')],
                ]);
            }

            // Client-supplied prices are ignored — resolver is SSOT for estimates.
            $resolved = $this->pricing->resolvePrice($customer, $offering, $onDate);
            $product = $offering->product;
            $treatment = $offering->treatment;
            $category = $product?->category;

            $built[] = [
                'offering_id' => $offering->id,
                'category_id' => $category?->id,
                'product_id' => $product?->id,
                'treatment_id' => $treatment?->id,
                'quantity' => $row['quantity'],
                'unit_price_estimate' => $resolved['unit_price'],
                'currency' => $resolved['currency'],
                'price_source' => $resolved['source'],
                'contract_id' => $resolved['source'] === 'CONTRACT' ? $contractId : null,
                'product_name_ar' => $product?->name_ar ?? '',
                'product_name_en' => $product?->name_en ?? '',
                'treatment_name_ar' => $treatment?->name_ar ?? '',
                'treatment_name_en' => $treatment?->name_en ?? '',
                'category_name_ar' => $category?->name_ar,
                'category_name_en' => $category?->name_en,
            ];
        }

        return $built;
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function sumEstimates(array $lines): string
    {
        $sum = '0';
        foreach ($lines as $line) {
            $lineTotal = GhMoney::mul(
                GhMoney::of((string) $line['unit_price_estimate']),
                (string) (int) $line['quantity'],
            );
            $sum = GhMoney::add($sum, $lineTotal);
        }

        return GhMoney::roundHalfUp($sum);
    }
}
