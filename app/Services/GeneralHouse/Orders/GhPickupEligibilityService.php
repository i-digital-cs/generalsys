<?php

namespace App\Services\GeneralHouse\Orders;

use App\Support\GeneralHouse\GhOrderStatus;
use Illuminate\Support\Carbon;

/**
 * Shared LATER → PICKUP eligibility: an order becomes pickup-eligible at 00:00 of its
 * scheduled pickup calendar date in the GH business timezone (Asia/Dubai).
 *
 * Wall-clock inputs/displays use the GH business timezone.
 * Canonical storage is UTC (GhUtcDateTime). Instant comparisons use absolute Carbon values.
 * Does not change config('app.timezone').
 */
class GhPickupEligibilityService
{
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

    /**
     * Format an instant for SQL comparison against UTC-stored TIMESTAMP columns.
     * Avoids Query Builder formatting in app.timezone (Africa/Cairo).
     */
    public function toUtcSql(Carbon $instant): string
    {
        return $instant->copy()->utc()->format('Y-m-d H:i:s');
    }

    /** Instant (UTC) when the order's pickup calendar date begins in the business timezone. */
    public function eligibleFrom(Carbon $pickupStartsAt): Carbon
    {
        return $pickupStartsAt->copy()->timezone($this->timezone())->startOfDay()->utc();
    }

    public function isEligibleForPickup(Carbon $pickupStartsAt, ?Carbon $now = null): bool
    {
        $now ??= $this->now();

        return $now->greaterThanOrEqualTo($this->eligibleFrom($pickupStartsAt));
    }

    /**
     * UTC SQL bound: LATER orders with pickup_starts_at strictly below this are eligible
     * (pickup local date ≤ today in the business timezone).
     */
    public function eligibleCutoffUtcSql(?Carbon $now = null): string
    {
        $now ??= $this->now();

        return $this->toUtcSql($now->copy()->timezone($this->timezone())->startOfDay()->addDay());
    }

    public function initialStatus(Carbon $pickupStartsAt, ?Carbon $now = null): string
    {
        return $this->isEligibleForPickup($pickupStartsAt, $now)
            ? GhOrderStatus::PICKUP
            : GhOrderStatus::LATER;
    }
}
