<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * Top-level company modules (T-9.1). HR is the existing app; Service/Product have their own sidebars.
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
     * @return list<string>
     */
    public static function navItems(?User $user): array
    {
        $items = self::NAV;

        if ($user?->isManager()) {
            $items[] = self::SETTINGS;
        }

        return $items;
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

        if ($name === 'portal.product' || str_starts_with($name, 'product.')) {
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
