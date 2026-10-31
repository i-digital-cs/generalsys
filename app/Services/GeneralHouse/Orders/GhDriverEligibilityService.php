<?php

namespace App\Services\GeneralHouse\Orders;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class GhDriverEligibilityService
{
    /**
     * @return Collection<int, User>
     */
    public function eligibleDrivers(): Collection
    {
        return User::query()
            ->where('role', User::ROLE_DRIVER)
            ->where('status', User::STATUS_ACTIVE)
            ->orderBy('name_ar')
            ->get(['id', 'name_ar', 'name_en', 'role', 'status']);
    }

    public function assertEligible(?int $userId, string $field = 'pickup_driver_id'): User
    {
        if ($userId === null || $userId < 1) {
            throw ValidationException::withMessages([
                $field => [__('gh.errors.driver_required')],
            ]);
        }

        $user = User::query()->find($userId);
        if ($user === null || ! $user->isDriver() || ! $user->isActive()) {
            throw ValidationException::withMessages([
                $field => [__('gh.errors.driver_ineligible')],
            ]);
        }

        return $user;
    }

    public function assertOptionalEligible(?int $userId, string $field = 'delivery_driver_id'): ?User
    {
        if ($userId === null || $userId < 1) {
            return null;
        }

        return $this->assertEligible($userId, $field);
    }
}
