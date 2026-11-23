<?php

namespace App\Services\GeneralHouse\Catalog;

use App\Models\GeneralHouse\GhProductOffering;
use App\Models\GeneralHouse\GhServiceCategory;
use App\Models\GeneralHouse\GhTreatment;
use App\Support\GeneralHouse\GhCatalogStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GhCategoryTreatmentService
{
    /** @return Collection<int, GhTreatment> */
    public function treatmentsForCategory(int $categoryId, bool $activeOnly = true): Collection
    {
        $q = GhTreatment::query()
            ->whereHas('categories', fn ($c) => $c->where('gh_service_categories.id', $categoryId))
            ->orderBy('sort_order')
            ->orderBy('name_en');

        if ($activeOnly) {
            $q->where('status', GhCatalogStatus::ACTIVE);
        }

        return $q->get();
    }

    /**
     * Map of category_id => list of treatment payloads for the product form.
     *
     * @return array<string, list<array{id: int, name_ar: string, name_en: string, status: string, sort_order: int}>>
     */
    public function catalogPayload(): array
    {
        $categories = GhServiceCategory::query()
            ->with(['treatments' => fn ($q) => $q->orderBy('gh_treatments.sort_order')->orderBy('gh_treatments.name_en')])
            ->orderBy('sort_order')
            ->orderBy('name_en')
            ->get();

        $payload = [];
        foreach ($categories as $category) {
            $payload[(string) $category->id] = $category->treatments
                ->map(fn (GhTreatment $t) => [
                    'id' => (int) $t->id,
                    'name_ar' => (string) $t->name_ar,
                    'name_en' => (string) $t->name_en,
                    'status' => (string) $t->status,
                    'sort_order' => (int) $t->sort_order,
                ])
                ->values()
                ->all();
        }

        return $payload;
    }

    public function isMapped(int $categoryId, int $treatmentId): bool
    {
        return DB::table('gh_category_treatment')
            ->where('category_id', $categoryId)
            ->where('treatment_id', $treatmentId)
            ->exists();
    }

    /**
     * @param  list<int>  $treatmentIds
     */
    public function sync(GhServiceCategory $category, array $treatmentIds): void
    {
        $desired = collect($treatmentIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        foreach ($desired as $treatmentId) {
            if (! GhTreatment::query()->whereKey($treatmentId)->exists()) {
                throw ValidationException::withMessages([
                    'treatment_ids' => [__('gh.errors.treatment_missing')],
                ]);
            }
        }

        $current = $category->treatments()->pluck('gh_treatments.id')->map(fn ($id) => (int) $id);
        $toDetach = $current->diff($desired)->values();

        foreach ($toDetach as $treatmentId) {
            $this->assertCanDetach($category, (int) $treatmentId);
        }

        $sync = [];
        foreach ($desired as $index => $treatmentId) {
            $sync[$treatmentId] = ['sort_order' => $index];
        }

        $category->treatments()->sync($sync);
    }

    public function attach(GhServiceCategory $category, GhTreatment $treatment, int $sortOrder = 0): void
    {
        if ($this->isMapped((int) $category->id, (int) $treatment->id)) {
            return;
        }

        $category->treatments()->attach($treatment->id, ['sort_order' => $sortOrder]);
    }

    public function detach(GhServiceCategory $category, GhTreatment $treatment): void
    {
        $this->assertCanDetach($category, (int) $treatment->id);
        $category->treatments()->detach($treatment->id);
    }

    public function assertCanDetach(GhServiceCategory $category, int $treatmentId): void
    {
        $blocking = GhProductOffering::query()
            ->where('treatment_id', $treatmentId)
            ->where('status', GhCatalogStatus::ACTIVE)
            ->whereHas('product', fn ($p) => $p->where('category_id', $category->id))
            ->exists();

        if ($blocking) {
            throw ValidationException::withMessages([
                'treatment_ids' => [__('gh.errors.category_treatment_in_use')],
            ]);
        }
    }

    public function assertCompatible(int $categoryId, int $treatmentId, string $errorKey = 'treatment_id'): void
    {
        if (! $this->isMapped($categoryId, $treatmentId)) {
            throw ValidationException::withMessages([
                $errorKey => [__('gh.errors.treatment_not_in_category')],
            ]);
        }
    }
}
