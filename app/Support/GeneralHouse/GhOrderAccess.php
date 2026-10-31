<?php

namespace App\Support\GeneralHouse;

use App\Models\GeneralHouse\GhOrder;
use App\Models\User;

/**
 * GH-4 logistics authorization. Manager may elevate; drivers scoped by assignment.
 */
final class GhOrderAccess
{
    public static function mayManage(?User $user): bool
    {
        return GhCatalogAccess::mayManage($user);
    }

    public static function mayConfirmPickup(?User $user, GhOrder $order): bool
    {
        if ($user === null) {
            return false;
        }
        if (self::mayManage($user)) {
            return true;
        }

        return $user->isDriver()
            && $user->isActive()
            && (int) $order->pickup_driver_id === (int) $user->id;
    }

    public static function mayConfirmDelivery(?User $user, GhOrder $order): bool
    {
        if ($user === null) {
            return false;
        }
        if (self::mayManage($user)) {
            return true;
        }

        return $user->isDriver()
            && $user->isActive()
            && (int) $order->delivery_driver_id === (int) $user->id;
    }

    public static function mayRecordPickupAttempt(?User $user, GhOrder $order): bool
    {
        return self::mayConfirmPickup($user, $order);
    }

    public static function mayRecordDeliveryAttempt(?User $user, GhOrder $order): bool
    {
        return self::mayConfirmDelivery($user, $order);
    }
}
