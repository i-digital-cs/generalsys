<?php

namespace App\Services\GeneralHouse\Catalog;

use App\Models\GeneralHouse\GhServiceCategory;
use App\Support\GeneralHouse\GhCatalogStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class GhCategoryService
{
    /**
     * @param  array{q?: string, status?: string}  $filters
     * @return LengthAwarePaginator<int, GhServiceCategory>
     */
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $perPage = max(1, min(50, $perPage));
        $q = GhServiceCategory::query()->orderBy('sort_order')->orderBy('name_en');

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $like = '%'.$search.'%';
            $q->where(function ($inner) use ($like) {
                $inner->where('name_ar', 'like', $like)
                    ->orWhere('name_en', 'like', $like);
            });
        }

        $status = (string) ($filters['status'] ?? '');
        if (in_array($status, GhCatalogStatus::ALL, true)) {
            $q->where('status', $status);
        }

        return $q->paginate($perPage)->withQueryString();
    }

    /** @return Collection<int, GhServiceCategory> */
    public function activeOrdered()
    {
        return GhServiceCategory::query()
            ->where('status', GhCatalogStatus::ACTIVE)
            ->orderBy('sort_order')
            ->orderBy('name_en')
            ->get();
    }

    /** @return Collection<int, GhServiceCategory> */
    public function allOrdered()
    {
        return GhServiceCategory::query()
            ->orderBy('sort_order')
            ->orderBy('name_en')
            ->get();
    }

    /**
     * @param  array{name_ar: string, name_en: string, description_ar?: ?string, description_en?: ?string, status?: string, sort_order?: int}  $data
     */
    public function create(array $data): GhServiceCategory
    {
        return GhServiceCategory::query()->create([
            'name_ar' => $data['name_ar'],
            'name_en' => $data['name_en'],
            'description_ar' => $data['description_ar'] ?? null,
            'description_en' => $data['description_en'] ?? null,
            'status' => $data['status'] ?? GhCatalogStatus::ACTIVE,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);
    }

    /**
     * @param  array{name_ar: string, name_en: string, description_ar?: ?string, description_en?: ?string, status?: string, sort_order?: int}  $data
     */
    public function update(GhServiceCategory $category, array $data): GhServiceCategory
    {
        $category->update([
            'name_ar' => $data['name_ar'],
            'name_en' => $data['name_en'],
            'description_ar' => $data['description_ar'] ?? null,
            'description_en' => $data['description_en'] ?? null,
            'status' => $data['status'] ?? $category->status,
            'sort_order' => (int) ($data['sort_order'] ?? $category->sort_order),
        ]);

        return $category->refresh();
    }

    public function setStatus(GhServiceCategory $category, string $status): GhServiceCategory
    {
        if (! in_array($status, GhCatalogStatus::ALL, true)) {
            throw ValidationException::withMessages([
                'status' => [__('gh.errors.invalid_status')],
            ]);
        }

        $category->update(['status' => $status]);

        return $category->refresh();
    }

    public function delete(GhServiceCategory $category): void
    {
        if ($category->products()->exists()) {
            throw ValidationException::withMessages([
                'category' => [__('gh.errors.category_has_products')],
            ]);
        }

        $category->delete();
    }
}
