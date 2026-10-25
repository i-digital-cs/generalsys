<?php

namespace App\Services\GeneralHouse\Customers;

use App\Models\GeneralHouse\GhCustomer;
use App\Support\GeneralHouse\GhCatalogStatus;
use App\Support\GeneralHouse\GhCustomerType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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
     * @param  array{type: string, name_ar: string, name_en?: ?string, phone?: ?string, email?: ?string, billing_name?: ?string, billing_address?: ?string, tax_registration_number?: ?string, status?: string, notes?: ?string}  $data
     */
    public function create(array $data): GhCustomer
    {
        $type = $this->normalizeType($data['type']);

        return GhCustomer::query()->create([
            'type' => $type,
            'name_ar' => $data['name_ar'],
            'name_en' => $data['name_en'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'billing_name' => $data['billing_name'] ?? null,
            'billing_address' => $data['billing_address'] ?? null,
            'tax_registration_number' => $data['tax_registration_number'] ?? null,
            'status' => $data['status'] ?? GhCatalogStatus::ACTIVE,
            'notes' => $data['notes'] ?? null,
        ]);
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
