<?php

namespace App\Support;

use App\Models\User;

/**
 * Mohra Gross access helpers (docs/GROSS_MODULE.md §8).
 */
final class GrossAccess
{
    public static function mayAccessGross(?User $user): bool
    {
        return AppModule::mayAccessModule($user, AppModule::PRODUCT);
    }

    public static function mayManageMasterData(?User $user): bool
    {
        if ($user === null || ! self::mayAccessGross($user)) {
            return false;
        }

        return $user->isManager() || $user->isAccountant();
    }

    public static function mayAdjustStock(?User $user): bool
    {
        return self::mayManageMasterData($user);
    }

    public static function maySell(?User $user): bool
    {
        if ($user === null || ! self::mayAccessGross($user)) {
            return false;
        }

        return $user->isManager() || $user->isAccountant() || $user->isSales();
    }

    public static function mayRefuse(?User $user): bool
    {
        return self::maySell($user);
    }

    public static function mayConfirmLoss(?User $user): bool
    {
        if ($user === null || ! self::mayAccessGross($user)) {
            return false;
        }

        return $user->mayConfirmGrossLoss();
    }

    public static function mayCollectCourier(?User $user): bool
    {
        return self::maySell($user);
    }

    public static function mayPostTreasury(?User $user): bool
    {
        return self::mayManageMasterData($user);
    }

    /**
     * Sales-invoice update / delete (any status) and all other mutations — Manager (Admin) only.
     * Create stays on maySell so Sales can open drafts.
     */
    public static function mayAdminMutateSalesInvoice(?User $user): bool
    {
        if ($user === null || ! self::mayAccessGross($user)) {
            return false;
        }

        return $user->isManager();
    }

    /**
     * Purchase invoice update / delete (any status) — Manager (Admin) only.
     * Create / confirm / returns stay on mayManageMasterData (Manager + Accountant).
     */
    public static function mayAdminMutatePurchaseInvoice(?User $user): bool
    {
        if ($user === null || ! self::mayAccessGross($user)) {
            return false;
        }

        return $user->isManager();
    }
}
