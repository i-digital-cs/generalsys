<?php

namespace App\Services\GeneralHouse\Orders;

use App\Support\GeneralHouse\GhOrderStatus;
use Illuminate\Support\Carbon;

/**
 * Shared eligibility: pickup eligible when now >= pickup_starts_at − lead hours.
 *
 * Wall-clock inputs/displays use GH business timezone (Asia/Dubai).
 * Instant comparisons use absolute Carbon values (do not depend on tz label).
 * Does not change config('app.timezone').
 */
class GhPickupEligibilityService
{
    public function leadHours(): int
    {
        return max(1, (int) config('general_house.pickup_eligibility_lead_hours', 24));
    }

    /** GH business zone for parsing/display of local pickup windows. */
    public function timezone(): string
    {
        return (string) config('general_house.timezone', 'Asia/Dubai');
    }

    /**
     * Zone for displaying an order window — prefer the row's captured zone.
     */
    public function displayTimezone(?string $orderTimezone): string
    {
        $stored = trim((string) $orderTimezone);
        if ($stored !== '') {
            return $stored;
        }

        return $this->timezone();
    }

    /** Absolute "now" (respects Carbon::setTestNow). */
    public function now(): Carbon
    {
        return Carbon::now();
    }

    public function isEligibleForPickup(Carbon $pickupStartsAt, ?Carbon $now = null): bool
    {
        $now ??= $this->now();
        $threshold = $pickupStartsAt->copy()->subHours($this->leadHours());

        return $now->greaterThanOrEqualTo($threshold);
    }

    public function initialStatus(Carbon $pickupStartsAt, ?Carbon $now = null): string
    {
        return $this->isEligibleForPickup($pickupStartsAt, $now)
            ? GhOrderStatus::PICKUP
            : GhOrderStatus::LATER;
    }
}
