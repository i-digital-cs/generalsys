<?php

namespace App\Services\GeneralHouse\Orders;

use App\Support\GeneralHouse\GhOrderStatus;
use Illuminate\Support\Carbon;

/**
 * Shared eligibility rule for GH-3 initial status and future GH-4 scheduler.
 * Pickup eligible when now >= pickup_starts_at − 24 hours (app timezone).
 */
class GhPickupEligibilityService
{
    public const LEAD_HOURS = 24;

    public function timezone(): string
    {
        return (string) config('app.timezone', 'Africa/Cairo');
    }

    public function now(): Carbon
    {
        return Carbon::now($this->timezone());
    }

    public function isEligibleForPickup(Carbon $pickupStartsAt, ?Carbon $now = null): bool
    {
        $now ??= $this->now();
        $threshold = $pickupStartsAt->copy()->timezone($this->timezone())->subHours(self::LEAD_HOURS);

        return $now->greaterThanOrEqualTo($threshold);
    }

    /**
     * Initial logistics status at order create (no scheduler job in GH-3).
     */
    public function initialStatus(Carbon $pickupStartsAt, ?Carbon $now = null): string
    {
        return $this->isEligibleForPickup($pickupStartsAt, $now)
            ? GhOrderStatus::PICKUP
            : GhOrderStatus::LATER;
    }
}
