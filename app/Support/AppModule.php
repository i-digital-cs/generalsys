<?php

namespace App\Support;

use App\Models\User;
use App\Models\ValuationAssignment;
use Illuminate\Http\Request;

/**
 * Top-level company modules (T-9.1). HR is the existing app; Service/Product have their own sidebars.
 * Service/Product appear only when assigned (managers always see both for oversight).
 */
final class AppModule
{
    public const HOME = 'home';

    public const SERVICE = 'service';

    public const PRODUCT = 'product';

    public const HR = 'hr';

    public const SETTINGS = 'settings';

    /** @var list<string> */
    public const NAV = [self::SERVICE, self::PRODUCT, self::HR];

    /**
     * Navbar / home module tabs visible to this user.
     *
     * @return list<string>
     */
    public static function navItems(?User $user): array
    {
        $items = [];

        if ($user !== null) {
            if (self::mayAccessModule($user, self::SERVICE)) {
                $items[] = self::SERVICE;
            }
            if (self::mayAccessModule($user, self::PRODUCT)) {
                $items[] = self::PRODUCT;
            }
        }

        $items[] = self::HR;

        if ($user?->isManager()) {
            $items[] = self::SETTINGS;
        }

        return $items;
    }

    /**
     * Managers oversee both modules; everyone else needs a valuation assignment.
     */
    public static function mayAccessModule(?User $user, string $module): bool
    {
        if ($user === null || ! in_array($module, ValuationAssignment::MODULES, true)) {
            return false;
        }

        if ($user->isManager()) {
            return true;
        }

        if ($user->relationLoaded('valuationAssignments')) {
            return $user->valuationAssignments->contains('module', $module);
        }

        return $user->valuationAssignments()->where('module', $module)->exists();
    }

    public static function fromRequest(Request $request): string
    {
        $name = (string) $request->route()?->getName();

        if ($name === 'portal.home') {
            return self::HOME;
        }

        if ($name === 'portal.service' || str_starts_with($name, 'service.')) {
            return self::SERVICE;
        }

        if ($name === 'portal.product' || str_starts_with($name, 'product.')
            || str_starts_with($name, 'gross.')) {
            return self::PRODUCT;
        }

        if ($name === 'admin.settings' || str_starts_with($name, 'admin.settings.')
            || str_starts_with($name, 'valuation.assignments')) {
            return self::SETTINGS;
        }

        $routeModule = (string) $request->route('module');
        if ($routeModule === self::SERVICE || $routeModule === self::PRODUCT) {
            return $routeModule;
        }

        return self::HR;
    }

    public static function showsSidebar(string $module): bool
    {
        return in_array($module, [self::HR, self::SERVICE, self::PRODUCT], true);
    }

    public static function navRoute(string $module): string
    {
        return match ($module) {
            self::SERVICE => 'portal.service',
            self::PRODUCT => 'portal.product',
            self::SETTINGS => 'admin.settings',
            default => 'portal.hr',
        };
    }
}
