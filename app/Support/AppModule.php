<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * Top-level company modules (T-9.1). HR is the existing app; Service/Production are shells.
 */
final class AppModule
{
    public const HOME = 'home';

    public const SERVICE = 'service';

    public const PRODUCTION = 'production';

    public const HR = 'hr';

    public const SETTINGS = 'settings';

    /** @var list<string> */
    public const NAV = [self::SERVICE, self::PRODUCTION, self::HR];

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

        if ($name === 'portal.production' || str_starts_with($name, 'production.')) {
            return self::PRODUCTION;
        }

        if ($name === 'admin.settings' || str_starts_with($name, 'admin.settings.')) {
            return self::SETTINGS;
        }

        return self::HR;
    }

    public static function showsHrSidebar(string $module): bool
    {
        return $module === self::HR;
    }

    public static function navRoute(string $module): string
    {
        return match ($module) {
            self::SERVICE => 'portal.service',
            self::PRODUCTION => 'portal.production',
            self::SETTINGS => 'admin.settings',
            default => 'portal.hr',
        };
    }
}
