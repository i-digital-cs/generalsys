<?php

namespace App\Services\GeneralHouse\Catalog;

use App\Models\GeneralHouse\GhProduct;
use App\Models\GeneralHouse\GhProductOffering;
use App\Models\GeneralHouse\GhTreatment;
use App\Support\GeneralHouse\GhCatalogStatus;
use App\Support\GeneralHouse\GhMoney;
use App\Support\GeneralHouse\GhTaxClass;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

class GhOfferingService
{
    /**
     * @param  array{q?: string, status?: string, product_id?: int|string, treatment_id?: int|string}  $filters
     * @return LengthAwarePaginator<int, GhProductOffering>
     */
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $perPage = max(1, min(50, $perPage));
        $q = GhProductOffering::query()
            ->with(['product.category', 'treatment'])
            ->orderBy('sort_order')
            ->orderBy('id');

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $like = '%'.$search.'%';
            $q->where(function ($inner) use ($like) {
                $inner->whereHas('product', function ($p) use ($like) {
                    $p->where('name_ar', 'like', $like)
                        ->orWhere('name_en', 'like', $like)
                        ->orWhere('code', 'like', $like);
                })->orWhereHas('treatment', function ($t) use ($like) {
                    $t->where('name_ar', 'like', $like)
                        ->orWhere('name_en', 'like', $like);
                });
            });
        }

        $status = (string) ($filters['status'] ?? '');
        if (in_array($status, GhCatalogStatus::ALL, true)) {
            $q->where('status', $status);
        }

        if (($filters['product_id'] ?? '') !== '' && $filters['product_id'] !== null) {
            $q->where('product_id', (int) $filters['product_id']);
        }
        if (($filters['treatment_id'] ?? '') !== '' && $filters['treatment_id'] !== null) {
            $q->where('treatment_id', (int) $filters['treatment_id']);
        }

        return $q->paginate($perPage)->withQueryString();
    }

    /**
     * @param  array{product_id: int, treatment_id: int, standard_price: string|float|int, currency?: string, tax_class?: string, status?: string, sort_order?: int}  $data
     */
    public function create(array $data): GhProductOffering
    {
        $this->assertProductExists((int) $data['product_id']);
        $this->assertTreatmentExists((int) $data['treatment_id']);
        $price = $this->normalizePrice($data['standard_price'] ?? null);
        $taxClass = $this->normalizeTaxClass($data['tax_class'] ?? GhTaxClass::STANDARD_RATED);
        $currency = $this->normalizeCurrency($data['currency'] ?? 'AED');

        try {
            return GhProductOffering::query()->create([
                'product_id' => (int) $data['product_id'],
                'treatment_id' => (int) $data['treatment_id'],
                'standard_price' => $price,
                'currency' => $currency,
                'tax_class' => $taxClass,
                'status' => $data['status'] ?? GhCatalogStatus::ACTIVE,
                'sort_order' => (int) ($data['sort_order'] ?? 0),
            ]);
        } catch (QueryException $e) {
            if ($this->isUniqueViolation($e)) {
                throw ValidationException::withMessages([
                    'treatment_id' => [__('gh.errors.offering_duplicate')],
                ]);
            }
            throw $e;
        }
    }

    /**
     * @param  array{product_id: int, treatment_id: int, standard_price: string|float|int, currency?: string, tax_class?: string, status?: string, sort_order?: int}  $data
     */
    public function update(GhProductOffering $offering, array $data): GhProductOffering
    {
        $this->assertProductExists((int) $data['product_id']);
        $this->assertTreatmentExists((int) $data['treatment_id']);
        $price = $this->normalizePrice($data['standard_price'] ?? null);
        $taxClass = $this->normalizeTaxClass($data['tax_class'] ?? $offering->tax_class);
        $currency = $this->normalizeCurrency($data['currency'] ?? $offering->currency);

        try {
            $offering->update([
                'product_id' => (int) $data['product_id'],
                'treatment_id' => (int) $data['treatment_id'],
                'standard_price' => $price,
                'currency' => $currency,
                'tax_class' => $taxClass,
                'status' => $data['status'] ?? $offering->status,
                'sort_order' => (int) ($data['sort_order'] ?? $offering->sort_order),
            ]);
        } catch (QueryException $e) {
            if ($this->isUniqueViolation($e)) {
                throw ValidationException::withMessages([
                    'treatment_id' => [__('gh.errors.offering_duplicate')],
                ]);
            }
            throw $e;
        }

        return $offering->refresh();
    }

    public function setStatus(GhProductOffering $offering, string $status): GhProductOffering
    {
        if (! in_array($status, GhCatalogStatus::ALL, true)) {
            throw ValidationException::withMessages([
                'status' => [__('gh.errors.invalid_status')],
            ]);
        }

        $offering->update(['status' => $status]);

        return $offering->refresh();
    }

    public function delete(GhProductOffering $offering): void
    {
        $offering->delete();
    }

    private function assertProductExists(int $productId): void
    {
        if (! GhProduct::query()->whereKey($productId)->exists()) {
            throw ValidationException::withMessages([
                'product_id' => [__('gh.errors.product_missing')],
            ]);
        }
    }

    private function assertTreatmentExists(int $treatmentId): void
    {
        if (! GhTreatment::query()->whereKey($treatmentId)->exists()) {
            throw ValidationException::withMessages([
                'treatment_id' => [__('gh.errors.treatment_missing')],
            ]);
        }
    }

    private function normalizePrice(mixed $raw): string
    {
        if ($raw === null || $raw === '') {
            throw ValidationException::withMessages([
                'standard_price' => [__('gh.errors.price_required')],
            ]);
        }

        if (! is_numeric($raw)) {
            throw ValidationException::withMessages([
                'standard_price' => [__('gh.errors.price_invalid')],
            ]);
        }

        $value = GhMoney::of((string) $raw);
        if (GhMoney::cmp($value, '0') < 0) {
            throw ValidationException::withMessages([
                'standard_price' => [__('gh.errors.price_negative')],
            ]);
        }

        return $value;
    }

    private function normalizeTaxClass(string $taxClass): string
    {
        if (! in_array($taxClass, GhTaxClass::ALL, true)) {
            throw ValidationException::withMessages([
                'tax_class' => [__('gh.errors.tax_class_invalid')],
            ]);
        }

        return $taxClass;
    }

    private function normalizeCurrency(string $currency): string
    {
        $currency = strtoupper(trim($currency));
        if ($currency !== 'AED') {
            throw ValidationException::withMessages([
                'currency' => [__('gh.errors.currency_aed_only')],
            ]);
        }

        return $currency;
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, 'uq_gh_offering_product_treatment')
            || str_contains($message, 'UNIQUE constraint failed')
            || (string) $e->getCode() === '23000';
    }
}
