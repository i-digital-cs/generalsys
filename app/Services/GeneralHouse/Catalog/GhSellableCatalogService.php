<?php

namespace App\Services\GeneralHouse\Catalog;

use App\Models\GeneralHouse\GhProduct;
use App\Models\GeneralHouse\GhProductOffering;
use App\Support\GeneralHouse\GhCatalogStatus;
use Illuminate\Support\Collection;

/**
 * Phase 1 sellable surface: active offerings whose product, treatment, and
 * primary category are all ACTIVE. Invoice builder (Phase 5) must consume this
 * (or PricingResolver) — never query inactive catalog rows for new sales.
 */
class GhSellableCatalogService
{
    /** @return Collection<int, GhProductOffering> */
    public function activeOfferings(): Collection
    {
        return GhProductOffering::query()
            ->with(['product.category', 'treatment'])
            ->where('status', GhCatalogStatus::ACTIVE)
            ->whereHas('product', function ($q) {
                $q->where('status', GhCatalogStatus::ACTIVE)
                    ->whereHas('category', fn ($c) => $c->where('status', GhCatalogStatus::ACTIVE));
            })
            ->whereHas('treatment', fn ($q) => $q->where('status', GhCatalogStatus::ACTIVE))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * Active products in active categories (for laundry staff physical counting).
     * Includes products that do not yet have sellable offerings — UI must not invent offerings.
     *
     * @return Collection<int, GhProduct>
     */
    public function activeProductsForCounting(): Collection
    {
        return GhProduct::query()
            ->with([
                'category',
                'offerings' => function ($q) {
                    $q->where('status', GhCatalogStatus::ACTIVE)
                        ->whereHas('treatment', fn ($t) => $t->where('status', GhCatalogStatus::ACTIVE))
                        ->with('treatment')
                        ->orderBy('sort_order')
                        ->orderBy('id');
                },
            ])
            ->where('status', GhCatalogStatus::ACTIVE)
            ->whereHas('category', fn ($c) => $c->where('status', GhCatalogStatus::ACTIVE))
            ->orderBy('sort_order')
            ->orderBy('code')
            ->orderBy('id')
            ->get();
    }

    public function isSellable(GhProductOffering $offering): bool
    {
        $offering->loadMissing(['product.category', 'treatment']);

        return $offering->isActive()
            && $offering->product !== null
            && $offering->product->isActive()
            && $offering->product->category !== null
            && $offering->product->category->isActive()
            && $offering->treatment !== null
            && $offering->treatment->isActive();
    }
}
