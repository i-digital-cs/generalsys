<?php

namespace App\Services\GeneralHouse\Customers;

use App\Models\GeneralHouse\GhCustomer;
use App\Models\GeneralHouse\GhCustomerAddress;
use App\Models\GeneralHouse\GhZone;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GhCustomerAddressService
{
    /**
     * @param  array{zone_id: int, label: string, building?: ?string, street?: ?string, area?: ?string, directions?: ?string, latitude?: mixed, longitude?: mixed, is_default?: bool}  $data
     */
    public function create(GhCustomer $customer, array $data): GhCustomerAddress
    {
        $this->assertZone((int) $data['zone_id']);

        return DB::transaction(function () use ($customer, $data) {
            $makeDefault = (bool) ($data['is_default'] ?? false)
                || ! $customer->addresses()->exists();

            if ($makeDefault) {
                $this->clearDefaults($customer->id);
            }

            return GhCustomerAddress::query()->create([
                'customer_id' => $customer->id,
                'zone_id' => (int) $data['zone_id'],
                'label' => $data['label'],
                'building' => $data['building'] ?? null,
                'street' => $data['street'] ?? null,
                'area' => $data['area'] ?? null,
                'directions' => $data['directions'] ?? null,
                'latitude' => $this->nullableCoord($data['latitude'] ?? null),
                'longitude' => $this->nullableCoord($data['longitude'] ?? null),
                'is_default' => $makeDefault,
                'default_for_customer_id' => $makeDefault ? $customer->id : null,
            ]);
        });
    }

    /**
     * @param  array{zone_id: int, label: string, building?: ?string, street?: ?string, area?: ?string, directions?: ?string, latitude?: mixed, longitude?: mixed, is_default?: bool}  $data
     */
    public function update(GhCustomerAddress $address, array $data): GhCustomerAddress
    {
        $this->assertZone((int) $data['zone_id']);
        $this->assertBelongs($address);

        return DB::transaction(function () use ($address, $data) {
            $makeDefault = (bool) ($data['is_default'] ?? false);
            if ($makeDefault) {
                $this->clearDefaults($address->customer_id, $address->id);
            } elseif ($address->is_default && ! $makeDefault) {
                // Keep at least one default if this was the only one.
                $others = GhCustomerAddress::query()
                    ->where('customer_id', $address->customer_id)
                    ->where('id', '!=', $address->id)
                    ->count();
                if ($others === 0) {
                    $makeDefault = true;
                }
            }

            $address->update([
                'zone_id' => (int) $data['zone_id'],
                'label' => $data['label'],
                'building' => $data['building'] ?? null,
                'street' => $data['street'] ?? null,
                'area' => $data['area'] ?? null,
                'directions' => $data['directions'] ?? null,
                'latitude' => $this->nullableCoord($data['latitude'] ?? null),
                'longitude' => $this->nullableCoord($data['longitude'] ?? null),
                'is_default' => $makeDefault,
                'default_for_customer_id' => $makeDefault ? $address->customer_id : null,
            ]);

            return $address->refresh();
        });
    }

    public function makeDefault(GhCustomerAddress $address): GhCustomerAddress
    {
        return DB::transaction(function () use ($address) {
            $this->clearDefaults($address->customer_id, $address->id);
            $address->update([
                'is_default' => true,
                'default_for_customer_id' => $address->customer_id,
            ]);

            return $address->refresh();
        });
    }

    public function delete(GhCustomerAddress $address): void
    {
        DB::transaction(function () use ($address) {
            $customerId = $address->customer_id;
            $wasDefault = $address->is_default;
            $address->delete();

            if ($wasDefault) {
                $next = GhCustomerAddress::query()
                    ->where('customer_id', $customerId)
                    ->orderBy('id')
                    ->first();
                if ($next !== null) {
                    $next->update([
                        'is_default' => true,
                        'default_for_customer_id' => $customerId,
                    ]);
                }
            }
        });
    }

    private function clearDefaults(int $customerId, ?int $exceptId = null): void
    {
        $q = GhCustomerAddress::query()->where('customer_id', $customerId);
        if ($exceptId !== null) {
            $q->where('id', '!=', $exceptId);
        }
        $q->update([
            'is_default' => false,
            'default_for_customer_id' => null,
        ]);
    }

    private function assertZone(int $zoneId): void
    {
        if (! GhZone::query()->whereKey($zoneId)->exists()) {
            throw ValidationException::withMessages([
                'zone_id' => [__('gh.errors.zone_missing')],
            ]);
        }
    }

    private function assertBelongs(GhCustomerAddress $address): void
    {
        if ($address->customer_id < 1) {
            throw ValidationException::withMessages([
                'address' => [__('gh.errors.address_invalid')],
            ]);
        }
    }

    private function nullableCoord(mixed $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (! is_numeric($raw)) {
            throw ValidationException::withMessages([
                'latitude' => [__('gh.errors.coords_invalid')],
            ]);
        }

        return number_format((float) $raw, 7, '.', '');
    }
}
