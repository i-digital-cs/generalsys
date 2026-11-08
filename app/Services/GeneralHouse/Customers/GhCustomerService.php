<?php

namespace App\Services\GeneralHouse\Customers;

use App\Models\GeneralHouse\GhCustomer;
use App\Support\GeneralHouse\GhCatalogStatus;
use App\Support\GeneralHouse\GhCustomerType;
use App\Support\GeneralHouse\GhPhone;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GhCustomerService
{
    /**
     * @param  array{q?: string, status?: string, type?: string}  $filters
     * @return LengthAwarePaginator<int, GhCustomer>
     */
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $perPage = max(1, min(50, $perPage));
        $q = GhCustomer::query()->withCount('addresses')->orderBy('name_ar');

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $like = '%'.$search.'%';
            $q->where(function ($inner) use ($like) {
                $inner->where('name_ar', 'like', $like)
                    ->orWhere('name_en', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('tax_registration_number', 'like', $like);
            });
        }

        $status = (string) ($filters['status'] ?? '');
        if (in_array($status, GhCatalogStatus::ALL, true)) {
            $q->where('status', $status);
        }

        $type = (string) ($filters['type'] ?? '');
        if (in_array($type, GhCustomerType::ALL, true)) {
            $q->where('type', $type);
        }

        return $q->paginate($perPage)->withQueryString();
    }

    /**
     * Live lookup for create form: phone matches (normalized) + optional name suggestions.
     *
     * @return array{phone_matches: list<array<string, mixed>>, name_matches: list<array<string, mixed>>}
     */
    public function lookupDuplicates(?string $phone, ?string $nameAr = null, ?string $nameEn = null, ?int $exceptId = null): array
    {
        return [
            'phone_matches' => $this->findByNormalizedPhone($phone, $exceptId)
                ->map(fn (GhCustomer $c) => $this->duplicatePayload($c))
                ->values()
                ->all(),
            'name_matches' => $this->suggestByName($nameAr, $nameEn, $exceptId)
                ->map(fn (GhCustomer $c) => $this->duplicatePayload($c))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return Collection<int, GhCustomer>
     */
    public function findByNormalizedPhone(?string $phone, ?int $exceptId = null, bool $lockForUpdate = false): Collection
    {
        $normalized = GhPhone::normalize($phone);
        if ($normalized === null) {
            return collect();
        }

        // Narrow candidates by trailing local digits before PHP normalize filter.
        $suffix = strlen($normalized) >= 9 ? substr($normalized, -9) : $normalized;

        $q = GhCustomer::query()
            ->when($exceptId, fn ($inner) => $inner->where('id', '!=', $exceptId))
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->where('phone', 'like', '%'.$suffix.'%')
            ->orderBy('id');

        if ($lockForUpdate) {
            $q->lockForUpdate();
        }

        return $q->get()
            ->filter(fn (GhCustomer $c) => GhPhone::normalize($c->phone) === $normalized)
            ->values();
    }

    /**
     * @return Collection<int, GhCustomer>
     */
    public function suggestByName(?string $nameAr, ?string $nameEn, ?int $exceptId = null): Collection
    {
        $terms = collect([$nameAr, $nameEn])
            ->map(fn ($v) => trim((string) $v))
            ->filter(fn ($v) => mb_strlen($v) >= 3)
            ->unique()
            ->values();
        if ($terms->isEmpty()) {
            return collect();
        }

        $q = GhCustomer::query()->when($exceptId, fn ($inner) => $inner->where('id', '!=', $exceptId));
        $q->where(function ($outer) use ($terms) {
            foreach ($terms as $term) {
                $like = '%'.$term.'%';
                $outer->orWhere('name_ar', 'like', $like)->orWhere('name_en', 'like', $like);
            }
        });

        return $q->orderBy('id')->limit(8)->get();
    }

    /**
     * @param  array{type: string, name_ar: string, name_en?: ?string, phone?: ?string, email?: ?string, billing_name?: ?string, billing_address?: ?string, tax_registration_number?: ?string, status?: string, notes?: ?string, allow_duplicate_phone?: bool}  $data
     */
    public function create(array $data): GhCustomer
    {
        $type = $this->normalizeType($data['type']);
        $phone = isset($data['phone']) ? trim((string) $data['phone']) : null;
        $phone = $phone === '' ? null : $phone;
        $allowDuplicate = (bool) ($data['allow_duplicate_phone'] ?? false);

        return DB::transaction(function () use ($data, $type, $phone, $allowDuplicate) {
            // Re-check under row locks to reduce concurrent double-create races.
            $matches = $this->findByNormalizedPhone($phone, null, true);
            if ($matches->isNotEmpty() && ! $allowDuplicate) {
                throw ValidationException::withMessages([
                    'phone' => [__('gh.errors.customer_phone_exists')],
                ]);
            }

            return GhCustomer::query()->create([
                'type' => $type,
                'name_ar' => $data['name_ar'],
                'name_en' => $data['name_en'] ?? null,
                'phone' => $phone,
                'email' => $data['email'] ?? null,
                'billing_name' => $data['billing_name'] ?? null,
                'billing_address' => $data['billing_address'] ?? null,
                'tax_registration_number' => $data['tax_registration_number'] ?? null,
                'status' => $data['status'] ?? GhCatalogStatus::ACTIVE,
                'notes' => $data['notes'] ?? null,
            ]);
        });
    }

    /** @return array{id: int, type: string, name: string, phone: ?string, url: string} */
    private function duplicatePayload(GhCustomer $customer): array
    {
        return [
            'id' => (int) $customer->id,
            'type' => (string) $customer->type,
            'name' => $customer->localizedName(),
            'phone' => $customer->phone,
            'url' => route('gh.customers.show', $customer),
        ];
    }

    /**
     * @param  array{type: string, name_ar: string, name_en?: ?string, phone?: ?string, email?: ?string, billing_name?: ?string, billing_address?: ?string, tax_registration_number?: ?string, status?: string, notes?: ?string}  $data
     */
    public function update(GhCustomer $customer, array $data): GhCustomer
    {
        $type = $this->normalizeType($data['type']);
        if ($customer->type === GhCustomerType::B2B && $type === GhCustomerType::B2C && $customer->contracts()->exists()) {
            throw ValidationException::withMessages([
                'type' => [__('gh.errors.customer_has_contracts')],
            ]);
        }

        $customer->update([
            'type' => $type,
            'name_ar' => $data['name_ar'],
            'name_en' => $data['name_en'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'billing_name' => $data['billing_name'] ?? null,
            'billing_address' => $data['billing_address'] ?? null,
            'tax_registration_number' => $data['tax_registration_number'] ?? null,
            'status' => $data['status'] ?? $customer->status,
            'notes' => $data['notes'] ?? null,
        ]);

        return $customer->refresh();
    }

    public function delete(GhCustomer $customer): void
    {
        if ($customer->addresses()->exists() || $customer->contracts()->exists()) {
            throw ValidationException::withMessages([
                'customer' => [__('gh.errors.customer_has_history')],
            ]);
        }

        $customer->delete();
    }

    private function normalizeType(string $type): string
    {
        if (! in_array($type, GhCustomerType::ALL, true)) {
            throw ValidationException::withMessages([
                'type' => [__('gh.errors.customer_type_invalid')],
            ]);
        }

        return $type;
    }
}
