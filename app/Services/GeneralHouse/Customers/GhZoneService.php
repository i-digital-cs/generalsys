<?php

namespace App\Services\GeneralHouse\Customers;

use App\Models\GeneralHouse\GhZone;
use App\Support\GeneralHouse\GhCatalogStatus;
use App\Support\GeneralHouse\GhMinOrderPolicy;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class GhZoneService
{
    /**
     * @param  array{q?: string, status?: string}  $filters
     * @return LengthAwarePaginator<int, GhZone>
     */
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $perPage = max(1, min(50, $perPage));
        $q = GhZone::query()->orderBy('sort_order')->orderBy('name_en');

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $like = '%'.$search.'%';
            $q->where(function ($inner) use ($like) {
                $inner->where('name_ar', 'like', $like)->orWhere('name_en', 'like', $like);
            });
        }

        $status = (string) ($filters['status'] ?? '');
        if (in_array($status, GhCatalogStatus::ALL, true)) {
            $q->where('status', $status);
        }

        return $q->paginate($perPage)->withQueryString();
    }

    /** @return Collection<int, GhZone> */
    public function activeOrdered(): Collection
    {
        return GhZone::query()
            ->where('status', GhCatalogStatus::ACTIVE)
            ->orderBy('sort_order')
            ->orderBy('name_en')
            ->get();
    }

    /** @return Collection<int, GhZone> */
    public function allOrdered(): Collection
    {
        return GhZone::query()->orderBy('sort_order')->orderBy('name_en')->get();
    }

    /**
     * @param  array{name_ar: string, name_en: string, min_order_amount?: mixed, min_order_policy?: string, status?: string, sort_order?: int}  $data
     */
    public function create(array $data): GhZone
    {
        return GhZone::query()->create([
            'name_ar' => $data['name_ar'],
            'name_en' => $data['name_en'],
            'min_order_amount' => $this->normalizeAmount($data['min_order_amount'] ?? 0),
            'min_order_policy' => $this->normalizePolicy($data['min_order_policy'] ?? GhMinOrderPolicy::DEFAULT),
            'status' => $data['status'] ?? GhCatalogStatus::ACTIVE,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);
    }

    /**
     * @param  array{name_ar: string, name_en: string, min_order_amount?: mixed, min_order_policy?: string, status?: string, sort_order?: int}  $data
     */
    public function update(GhZone $zone, array $data): GhZone
    {
        $zone->update([
            'name_ar' => $data['name_ar'],
            'name_en' => $data['name_en'],
            'min_order_amount' => $this->normalizeAmount($data['min_order_amount'] ?? $zone->min_order_amount),
            'min_order_policy' => $this->normalizePolicy($data['min_order_policy'] ?? $zone->min_order_policy),
            'status' => $data['status'] ?? $zone->status,
            'sort_order' => (int) ($data['sort_order'] ?? $zone->sort_order),
        ]);

        return $zone->refresh();
    }

    public function delete(GhZone $zone): void
    {
        if ($zone->addresses()->exists()) {
            throw ValidationException::withMessages([
                'zone' => [__('gh.errors.zone_has_addresses')],
            ]);
        }

        $zone->delete();
    }

    private function normalizeAmount(mixed $raw): string
    {
        if (! is_numeric($raw) || (float) $raw < 0) {
            throw ValidationException::withMessages([
                'min_order_amount' => [__('gh.errors.min_order_amount_invalid')],
            ]);
        }

        return number_format(round((float) $raw, 2), 2, '.', '');
    }

    private function normalizePolicy(string $policy): string
    {
        if (! in_array($policy, GhMinOrderPolicy::ALL, true)) {
            throw ValidationException::withMessages([
                'min_order_policy' => [__('gh.errors.min_order_policy_invalid')],
            ]);
        }

        return $policy;
    }
}
