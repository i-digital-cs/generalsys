<?php

namespace App\Services\GeneralHouse\Catalog;

use App\Models\GeneralHouse\GhTreatment;
use App\Support\GeneralHouse\GhCatalogStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class GhTreatmentService
{
    /**
     * @param  array{q?: string, status?: string}  $filters
     * @return LengthAwarePaginator<int, GhTreatment>
     */
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $perPage = max(1, min(50, $perPage));
        $q = GhTreatment::query()->orderBy('sort_order')->orderBy('name_en');

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

    /** @return Collection<int, GhTreatment> */
    public function activeOrdered()
    {
        return GhTreatment::query()
            ->where('status', GhCatalogStatus::ACTIVE)
            ->orderBy('sort_order')
            ->orderBy('name_en')
            ->get();
    }

    /**
     * @param  array{name_ar: string, name_en: string, status?: string, sort_order?: int}  $data
     */
    public function create(array $data): GhTreatment
    {
        return GhTreatment::query()->create([
            'name_ar' => $data['name_ar'],
            'name_en' => $data['name_en'],
            'status' => $data['status'] ?? GhCatalogStatus::ACTIVE,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);
    }

    /**
     * @param  array{name_ar: string, name_en: string, status?: string, sort_order?: int}  $data
     */
    public function update(GhTreatment $treatment, array $data): GhTreatment
    {
        $treatment->update([
            'name_ar' => $data['name_ar'],
            'name_en' => $data['name_en'],
            'status' => $data['status'] ?? $treatment->status,
            'sort_order' => (int) ($data['sort_order'] ?? $treatment->sort_order),
        ]);

        return $treatment->refresh();
    }

    public function setStatus(GhTreatment $treatment, string $status): GhTreatment
    {
        if (! in_array($status, GhCatalogStatus::ALL, true)) {
            throw ValidationException::withMessages([
                'status' => [__('gh.errors.invalid_status')],
            ]);
        }

        $treatment->update(['status' => $status]);

        return $treatment->refresh();
    }

    public function delete(GhTreatment $treatment): void
    {
        if ($treatment->offerings()->exists()) {
            throw ValidationException::withMessages([
                'treatment' => [__('gh.errors.treatment_has_offerings')],
            ]);
        }

        $treatment->delete();
    }
}
