<?php

namespace App\Support;

use App\Models\User;

/**
 * Mohra Gross access helpers (docs/GROSS_MODULE.md §8).
 */
final class GrossAccess
{
    /** @var array<int, list<string>> */
    private static array $pageKeyCache = [];

    public static function mayAccessGross(?User $user): bool
    {
        return AppModule::mayAccessModule($user, AppModule::PRODUCT);
    }

    /**
     * Sidebar / route page gate. Manager always; others need Product + assigned page_key.
     */
    public static function mayPage(?User $user, string $pageKey): bool
    {
        if ($user === null || ! self::mayAccessGross($user)) {
            return false;
        }

        if ($user->isManager()) {
            return true;
        }

        $allowed = config('gross_pages.keys', []);
        if (! in_array($pageKey, $allowed, true)) {
            return false;
        }

        return in_array($pageKey, self::pageKeysFor($user), true);
    }

    /**
     * @return list<string>
     */
    public static function pageKeysFor(User $user): array
    {
        if ($user->isManager()) {
            return config('gross_pages.keys', []);
        }

        if (isset(self::$pageKeyCache[$user->id])) {
            return self::$pageKeyCache[$user->id];
        }

        if ($user->relationLoaded('grossPages')) {
            $keys = $user->grossPages->pluck('page_key')->map(fn ($k) => (string) $k)->values()->all();
        } else {
            $keys = $user->grossPages()->pluck('page_key')->map(fn ($k) => (string) $k)->values()->all();
        }

        return self::$pageKeyCache[$user->id] = $keys;
    }

    public static function clearPageKeyCache(?User $user = null): void
    {
        if ($user === null) {
            self::$pageKeyCache = [];

            return;
        }

        unset(self::$pageKeyCache[$user->id]);
    }

    /** @deprecated Prefer mayPage — Manager always; others by checklist. */
    public static function mayManageMasterData(?User $user): bool
    {
        if ($user === null || ! self::mayAccessGross($user)) {
            return false;
        }

        return $user->isManager()
            || self::mayPage($user, 'items')
            || self::mayPage($user, 'purchases')
            || self::mayPage($user, 'treasury');
    }

    public static function mayAdjustStock(?User $user): bool
    {
        return self::mayPage($user, 'stock');
    }

    public static function maySell(?User $user): bool
    {
        return self::mayPage($user, 'sales');
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
        return self::mayPage($user, 'treasury');
    }

    /**
     * Sales-invoice update / delete — Manager (Admin) only.
     */
    public static function mayAdminMutateSalesInvoice(?User $user): bool
    {
        if ($user === null || ! self::mayAccessGross($user)) {
            return false;
        }

        return $user->isManager();
    }

    /**
     * Purchase invoice update / delete — Manager (Admin) only.
     */
    public static function mayAdminMutatePurchaseInvoice(?User $user): bool
    {
        if ($user === null || ! self::mayAccessGross($user)) {
            return false;
        }

        return $user->isManager();
    }
}
