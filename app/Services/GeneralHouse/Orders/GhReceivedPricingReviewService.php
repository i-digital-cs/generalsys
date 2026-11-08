<?php

namespace App\Services\GeneralHouse\Orders;

use App\Models\GeneralHouse\GhOrder;
use App\Models\GeneralHouse\GhOrderReceivedLine;
use App\Models\GeneralHouse\GhProductOffering;
use App\Models\User;
use App\Services\GeneralHouse\Catalog\GhPricingResolver;
use App\Support\GeneralHouse\GhMoney;
use App\Support\GeneralHouse\GhOrderStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Staff resolves B2B out-of-contract received garments during WORKING (before invoice).
 */
class GhReceivedPricingReviewService
{
    public function __construct(private readonly GhPricingResolver $pricing) {}

    /**
     * @param  array{offering_id?: int, unit_price: string|float, notes?: ?string}  $data
     */
    public function approve(GhOrder $order, GhOrderReceivedLine $line, array $data, User $actor): GhOrderReceivedLine
    {
        if ((int) $line->order_id !== (int) $order->id) {
            throw ValidationException::withMessages([
                'received_line' => [__('gh.errors.received_line_not_on_order')],
            ]);
        }

        if ($order->status !== GhOrderStatus::WORKING || $order->invoice_issued_at !== null) {
            throw ValidationException::withMessages([
                'order' => [__('gh.errors.pricing_review_wrong_status')],
            ]);
        }

        if (! $line->pricing_review_required && ! $line->staff_price_approved) {
            throw ValidationException::withMessages([
                'received_line' => [__('gh.errors.pricing_review_not_needed')],
            ]);
        }

        $offeringId = (int) ($data['offering_id'] ?? $line->offering_id);
        $offering = GhProductOffering::query()->with(['product.category', 'treatment'])->find($offeringId);
        if ($offering === null) {
            throw ValidationException::withMessages([
                'offering_id' => [__('gh.errors.offering_missing')],
            ]);
        }

        $unit = GhMoney::of((string) ($data['unit_price'] ?? ''));
        if (bccomp($unit, '0.00', 2) < 0) {
            throw ValidationException::withMessages([
                'unit_price' => [__('gh.errors.unit_price_invalid')],
            ]);
        }

        return DB::transaction(function () use ($order, $line, $offering, $unit, $data, $actor) {
            $locked = GhOrderReceivedLine::query()->whereKey($line->id)->lockForUpdate()->firstOrFail();
            $product = $offering->product;
            $treatment = $offering->treatment;
            $category = $product?->category;

            // If staff picked an in-contract offering, prefer the live contract rate unless they override.
            $onContract = $this->pricing->isOnActiveContract($order->customer, $offering, $order->pickup_starts_at);
            $source = 'STANDARD';
            $contractId = null;
            $finalUnit = $unit;
            if ($onContract) {
                $resolved = $this->pricing->resolvePrice($order->customer, $offering, $order->pickup_starts_at);
                $source = $resolved['source'];
                $contractId = $source === 'CONTRACT' ? $order->contract_id : null;
                // Explicit staff unit_price always wins for out-of-contract clearance.
                if (bccomp($unit, '0.00', 2) === 0) {
                    $finalUnit = $resolved['unit_price'];
                }
            }

            $locked->forceFill([
                'offering_id' => $offering->id,
                'unit_price_estimate' => $finalUnit,
                'currency' => $offering->currency ?: 'AED',
                'price_source' => $source,
                'contract_id' => $contractId,
                'pricing_review_required' => false,
                'staff_price_approved' => true,
                'product_name_ar' => $product?->name_ar ?? $locked->product_name_ar,
                'product_name_en' => $product?->name_en ?? $locked->product_name_en,
                'treatment_name_ar' => $treatment?->name_ar ?? $locked->treatment_name_ar,
                'treatment_name_en' => $treatment?->name_en ?? $locked->treatment_name_en,
                'category_name_ar' => $category?->name_ar,
                'category_name_en' => $category?->name_en,
                'notes' => $data['notes'] ?? __('gh.orders.staff_price_approved_note', ['actor' => $actor->localizedName()]),
            ])->save();

            return $locked->refresh();
        });
    }
}
