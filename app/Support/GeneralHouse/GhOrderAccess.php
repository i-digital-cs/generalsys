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

    /** Assigned pickup or delivery driver (not finance / not unassigned). */
    public static function mayViewAssignedTask(?User $user, GhOrder $order): bool
    {
        if ($user === null || ! $user->isDriver() || ! $user->isActive()) {
            return false;
        }

        $uid = (int) $user->id;

        return (int) $order->pickup_driver_id === $uid
            || (int) $order->delivery_driver_id === $uid;
    }

    public static function mayUseDriverWorkspace(?User $user): bool
    {
        return $user !== null && $user->isDriver() && $user->isActive();
    }
}
