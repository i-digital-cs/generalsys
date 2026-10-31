<?php

namespace App\Services\GeneralHouse\Orders;

use App\Models\GeneralHouse\GhOrder;
use App\Models\GeneralHouse\GhOrderLine;
use App\Models\GeneralHouse\GhOrderReceivedLine;
use App\Models\GeneralHouse\GhProductOffering;
use App\Services\GeneralHouse\Catalog\GhPricingResolver;
use App\Support\GeneralHouse\GhReceivedDiscrepancy;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Builds auditable received lines at pickup. Never mutates requested order lines.
 */
class GhReceivedItemService
{
    public function __construct(
        private readonly GhPricingResolver $pricing,
    ) {}

    /**
     * @param  list<array{order_line_id?: int|null, offering_id: int, quantity: int, notes?: ?string}>  $received
     * @return list<array<string, mixed>>
     */
    public function buildReceivedSnapshots(GhOrder $order, array $received): array
    {
        if ($received === []) {
            throw ValidationException::withMessages([
                'received' => [__('gh.errors.received_required')],
            ]);
        }

        $order->loadMissing(['lines', 'customer']);
        $customer = $order->customer;
        if ($customer === null) {
            throw ValidationException::withMessages([
                'order' => [__('gh.errors.customer_missing')],
            ]);
        }

        $onDate = $order->pickup_starts_at ?? Carbon::now();
        $linesById = $order->lines->keyBy('id');
        $accountedLineIds = [];
        $built = [];
        $sort = 0;

        foreach ($received as $i => $row) {
            $offeringId = (int) ($row['offering_id'] ?? 0);
            $qty = (int) ($row['quantity'] ?? 0);
            $orderLineId = isset($row['order_line_id']) && $row['order_line_id'] !== '' && $row['order_line_id'] !== null
                ? (int) $row['order_line_id']
                : null;

            if ($offeringId < 1 || $qty < 0) {
                throw ValidationException::withMessages([
                    "received.$i" => [__('gh.errors.received_line_invalid')],
                ]);
            }

            /** @var GhOrderLine|null $requested */
            $requested = null;
            if ($orderLineId !== null) {
                $requested = $linesById->get($orderLineId);
                if ($requested === null || (int) $requested->order_id !== (int) $order->id) {
                    throw ValidationException::withMessages([
                        "received.$i.order_line_id" => [__('gh.errors.received_line_not_on_order')],
                    ]);
                }
                if ((int) $requested->offering_id !== $offeringId) {
                    throw ValidationException::withMessages([
                        "received.$i.offering_id" => [__('gh.errors.received_offering_mismatch')],
                    ]);
                }
                if (isset($accountedLineIds[$orderLineId])) {
                    throw ValidationException::withMessages([
                        "received.$i.order_line_id" => [__('gh.errors.received_line_duplicate')],
                    ]);
                }
                $accountedLineIds[$orderLineId] = true;
            }

            $offering = GhProductOffering::query()
                ->with(['product.category', 'treatment'])
                ->find($offeringId);
            if ($offering === null) {
                throw ValidationException::withMessages([
                    "received.$i.offering_id" => [__('gh.errors.offering_missing')],
                ]);
            }

            // Additional items (no order_line_id) must still be sellable / on contract.
            $resolved = $this->pricing->resolvePrice($customer, $offering, $onDate);
            $product = $offering->product;
            $treatment = $offering->treatment;
            $category = $product?->category;

            $discrepancy = GhReceivedDiscrepancy::ADDITIONAL;
            if ($requested !== null) {
                if ($qty === 0) {
                    $discrepancy = GhReceivedDiscrepancy::MISSING;
                } elseif ($qty === (int) $requested->quantity) {
                    $discrepancy = GhReceivedDiscrepancy::MATCH;
                } elseif ($qty < (int) $requested->quantity) {
                    $discrepancy = GhReceivedDiscrepancy::SHORT;
                } else {
                    $discrepancy = GhReceivedDiscrepancy::OVER;
                }
            } elseif ($qty < 1) {
                throw ValidationException::withMessages([
                    "received.$i.quantity" => [__('gh.errors.received_additional_qty')],
                ]);
            }

            $built[] = [
                'order_id' => $order->id,
                'order_line_id' => $requested?->id,
                'offering_id' => $offering->id,
                'quantity' => $qty,
                'discrepancy_type' => $discrepancy,
                'unit_price_estimate' => $resolved['unit_price'],
                'currency' => $resolved['currency'],
                'price_source' => $resolved['source'],
                'contract_id' => $resolved['source'] === 'CONTRACT' ? $order->contract_id : null,
                'product_name_ar' => $product?->name_ar ?? '',
                'product_name_en' => $product?->name_en ?? '',
                'treatment_name_ar' => $treatment?->name_ar ?? '',
                'treatment_name_en' => $treatment?->name_en ?? '',
                'category_name_ar' => $category?->name_ar,
                'category_name_en' => $category?->name_en,
                'notes' => $row['notes'] ?? null,
                'sort_order' => $sort++,
            ];
        }

        // Requested lines omitted from payload are recorded as MISSING (qty 0).
        foreach ($order->lines as $line) {
            if (isset($accountedLineIds[$line->id])) {
                continue;
            }
            $built[] = [
                'order_id' => $order->id,
                'order_line_id' => $line->id,
                'offering_id' => $line->offering_id,
                'quantity' => 0,
                'discrepancy_type' => GhReceivedDiscrepancy::MISSING,
                'unit_price_estimate' => $line->unit_price_estimate,
                'currency' => $line->currency,
                'price_source' => $line->price_source,
                'contract_id' => $line->contract_id,
                'product_name_ar' => $line->product_name_ar,
                'product_name_en' => $line->product_name_en,
                'treatment_name_ar' => $line->treatment_name_ar,
                'treatment_name_en' => $line->treatment_name_en,
                'category_name_ar' => $line->category_name_ar,
                'category_name_en' => $line->category_name_en,
                'notes' => __('gh.orders.received_omitted'),
                'sort_order' => $sort++,
            ];
        }

        return $built;
    }

    /**
     * @param  list<array<string, mixed>>  $snapshots
     */
    public function persist(GhOrder $order, array $snapshots): void
    {
        GhOrderReceivedLine::query()->where('order_id', $order->id)->delete();
        foreach ($snapshots as $row) {
            GhOrderReceivedLine::query()->create($row);
        }
    }
}
