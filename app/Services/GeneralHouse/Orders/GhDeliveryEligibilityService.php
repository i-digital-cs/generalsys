<?php

namespace App\Services\GeneralHouse\Orders;

use App\Models\GeneralHouse\GhOrder;
use App\Support\GeneralHouse\GhOrderStatus;
use Illuminate\Support\Carbon;

/**
 * Invoice-independent WORKING → DELIVERY timing rules (Asia/Dubai display, UTC storage).
 *
 * Automatic promotion requires a non-null scheduled_delivery_at appointment.
 * Same calendar day (pickup vs scheduled delivery): eligible at picked_up_at + 2 hours.
 * Future delivery day: eligible at 08:00 Asia/Dubai on scheduled_delivery_at's local date.
 * Never eligible without confirmed pickup (picked_up_at + WORKING).
 * Missing scheduled delivery: not auto-eligible (manual promote still allowed separately).
 */
final class GhDeliveryEligibilityService
{
    public const SAME_DAY_DELAY_HOURS = 2;

    public const FUTURE_DAY_HOUR = 8;

    public function __construct(
        private readonly GhPickupEligibilityService $pickup,
    ) {}

    public function timezone(): string
    {
        return $this->pickup->timezone();
    }

    public function displayTimezone(?string $orderTimezone = null): string
    {
        return $this->pickup->displayTimezone($orderTimezone);
    }

    public function now(): Carbon
    {
        return $this->pickup->now();
    }

    /**
     * True when automatic WORKING → DELIVERY timing rules are satisfied.
     */
    public function isEligibleForDelivery(GhOrder $order, ?Carbon $now = null): bool
    {
        if ($order->status !== GhOrderStatus::WORKING) {
            return false;
        }
        if ($order->picked_up_at === null) {
            return false;
        }
        if ($order->scheduled_delivery_at === null) {
            return false;
        }

        $now ??= $this->now();
        $eligibleAt = $this->eligibleAt($order);
        if ($eligibleAt === null) {
            return false;
        }

        return $now->greaterThanOrEqualTo($eligibleAt);
    }

    /**
     * Manual readiness: confirmed pickup + WORKING. Schedule optional.
     */
    public function isEligibleForManualDelivery(GhOrder $order): bool
    {
        return $order->status === GhOrderStatus::WORKING
            && $order->picked_up_at !== null;
    }

    /**
     * Instant (UTC-comparable Carbon) when automatic WORKING → DELIVERY becomes allowed.
     * Null when pickup missing or scheduled delivery appointment is missing.
     */
    public function eligibleAt(GhOrder $order): ?Carbon
    {
        if ($order->picked_up_at === null || $order->scheduled_delivery_at === null) {
            return null;
        }

        $tz = $this->displayTimezone($order->timezone);
        // Same-day vs future-day is based on actual pickup calendar day (not scheduled pickup window).
        $pickupDay = $order->picked_up_at->copy()->timezone($tz)->toDateString();
        $deliveryLocal = $order->scheduled_delivery_at->copy()->timezone($tz);
        $deliveryDay = $deliveryLocal->toDateString();

        if ($deliveryDay <= $pickupDay) {
            // Same calendar day as actual pickup: wait 2 hours after confirmed pickup.
            return $order->picked_up_at->copy()->utc()->addHours(self::SAME_DAY_DELAY_HOURS);
        }

        // Future day: 08:00 Asia/Dubai on the scheduled delivery date.
        return $deliveryLocal->copy()->startOfDay()->setTime(self::FUTURE_DAY_HOUR, 0)->utc();
    }

    /**
     * Build UTC instant from a local date + time (appointment). Does not invent time.
     */
    public function scheduledDeliveryFromLocal(string $dateYmd, string $timeHi, ?string $orderTimezone = null): Carbon
    {
        $tz = $this->displayTimezone($orderTimezone);
        try {
            $local = Carbon::createFromFormat('Y-m-d H:i', $dateYmd.' '.$timeHi, $tz);
        } catch (\Throwable) {
            $local = Carbon::parse($dateYmd.' '.$timeHi, $tz);
        }

        return $local->utc();
    }
}
