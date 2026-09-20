<?php

namespace App\Services\Gross;

use App\Models\Gross\GrossCategory;
use App\Models\Gross\GrossItem;
use Illuminate\Support\Collection;

class CashierCatalogService
{
    public const PRODUCT_GROUP_LIMIT = 60;

    public function __construct(
        private readonly CategoryService $categories,
    ) {}

    /**
     * @return list<array{id: int, name: string}>
     */
    public function categories(): array
    {
        return $this->categories->roots()
            ->map(fn (GrossCategory $category): array => [
                'id' => (int) $category->id,
                'name' => $category->localizedName(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, name: string, parent_id: int}>
     */
    public function subcategories(int $categoryId): array
    {
        if ($categoryId < 1) {
            return [];
        }

        $parent = GrossCategory::query()
            ->whereNull('parent_id')
            ->whereKey($categoryId)
            ->first();

        if ($parent === null) {
            return [];
        }

        return GrossCategory::query()
            ->where('parent_id', $parent->id)
            ->orderBy('name_en')
            ->get()
            ->map(fn (GrossCategory $category): array => [
                'id' => (int) $category->id,
                'name' => $category->localizedName(),
                'parent_id' => (int) $category->parent_id,
            ])
            ->values()
            ->all();
    }

    /**
     * ACTIVE items grouped by exact name within a subcategory (or global search).
     *
     * @return list<array{
     *     name: string,
     *     subcategory_id: int|null,
     *     category: string,
     *     subcategory: string,
     *     description: string|null,
     *     image_url: string|null,
     *     variant_count: int,
     *     price_from: string|null
     * }>
     */
    public function productGroups(?int $subcategoryId, ?string $query = null, int $limit = self::PRODUCT_GROUP_LIMIT): array
    {
        $limit = min(max($limit, 1), self::PRODUCT_GROUP_LIMIT);
        $q = GrossItem::query()
            ->where('status', GrossItem::STATUS_ACTIVE)
            ->with(['images', 'balance', 'category', 'subcategory'])
            ->orderBy('name')
            ->orderBy('id');

        if ($subcategoryId !== null && $subcategoryId > 0) {
            $q->where('subcategory_id', $subcategoryId);
        }

        $search = trim((string) $query);
        if ($search !== '') {
            $like = '%'.$search.'%';
            $q->where(function ($inner) use ($like): void {
                $inner->where('name', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhere('code', 'like', $like);
            });
        }

        /** @var Collection<int, GrossItem> $items */
        $items = $q->limit(500)->get();

        $groups = [];
        foreach ($items as $item) {
            $key = $item->name.'|'.(string) ($item->subcategory_id ?? '0');
            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'name' => (string) $item->name,
                    'subcategory_id' => $item->subcategory_id !== null ? (int) $item->subcategory_id : null,
                    'category' => $item->category?->localizedName() ?: '',
                    'subcategory' => $item->subcategory?->localizedName() ?: '',
                    'description' => $this->shortDescription($item->description),
                    'image_url' => $item->thumbnailUrl(),
                    'variant_count' => 0,
                    'price_from' => null,
                    '_min_price' => null,
                ];
            }

            $groups[$key]['variant_count']++;

            if ($groups[$key]['description'] === null) {
                $groups[$key]['description'] = $this->shortDescription($item->description);
            }

            if ($groups[$key]['image_url'] === null) {
                $groups[$key]['image_url'] = $item->thumbnailUrl();
            }

            if ($groups[$key]['category'] === '' && $item->category) {
                $groups[$key]['category'] = $item->category->localizedName();
            }
            if ($groups[$key]['subcategory'] === '' && $item->subcategory) {
                $groups[$key]['subcategory'] = $item->subcategory->localizedName();
            }

            $price = $item->default_sale_price !== null ? (float) $item->default_sale_price : null;
            if ($price !== null && ($groups[$key]['_min_price'] === null || $price < $groups[$key]['_min_price'])) {
                $groups[$key]['_min_price'] = $price;
                $groups[$key]['price_from'] = number_format($price, 2, '.', '');
            }
        }

        return collect($groups)
            ->map(function (array $group): array {
                unset($group['_min_price']);

                return $group;
            })
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * Sibling SKUs for a product name (+ optional subcategory).
     *
     * @return list<array{
     *     id: int,
     *     code: string,
     *     name: string,
     *     size: string,
     *     color: string,
     *     model: string,
     *     sale_price: string|null,
     *     on_hand: int,
     *     image_url: string|null
     * }>
     */
    public function variants(string $name, ?int $subcategoryId = null): array
    {
        $name = trim($name);
        if ($name === '') {
            return [];
        }

        $q = GrossItem::query()
            ->where('status', GrossItem::STATUS_ACTIVE)
            ->where('name', $name)
            ->with(['images', 'balance', 'colorOption', 'sizeOption', 'modelOption'])
            ->orderBy('id');

        if ($subcategoryId !== null && $subcategoryId > 0) {
            $q->where('subcategory_id', $subcategoryId);
        }

        return $q->get()
            ->map(function (GrossItem $item): array {
                return [
                    'id' => (int) $item->id,
                    'code' => (string) $item->code,
                    'name' => (string) $item->name,
                    'size' => $item->sizeOption?->localizedName() ?: trim((string) ($item->size ?? '')),
                    'color' => $item->colorOption?->localizedName() ?: trim((string) ($item->color ?? '')),
                    'model' => $item->modelOption?->localizedName() ?: trim((string) ($item->model ?? '')),
                    'sale_price' => $item->default_sale_price !== null
                        ? number_format((float) $item->default_sale_price, 2, '.', '')
                        : null,
                    'on_hand' => (int) ($item->balance?->qty_on_hand_pieces ?? 0),
                    'image_url' => $item->thumbnailUrl(),
                ];
            })
            ->values()
            ->all();
    }

    private function shortDescription(?string $description): ?string
    {
        $text = trim((string) $description);
        if ($text === '') {
            return null;
        }

        if (mb_strlen($text) > 120) {
            return mb_substr($text, 0, 117).'…';
        }

        return $text;
    }
}
