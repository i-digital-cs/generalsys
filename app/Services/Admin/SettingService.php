<?php

namespace App\Services\Admin;

use App\Models\Setting;

/**
 * Global key/value settings (schema doc §19).
 * Payroll multipliers live in config/payroll.php only — per-employee OT/extra
 * overrides are edited on each payroll draft (not Admin Settings).
 */
class SettingService
{
    public const KEY_DEFAULT_LANGUAGE = 'default_language';

    public const KEY_DEFAULT_CURRENCY = 'default_currency';

    public const KEY_OFFICE_LATITUDE = 'office_latitude';

    public const KEY_OFFICE_LONGITUDE = 'office_longitude';

    public const KEY_OFFICE_RADIUS_METERS = 'office_radius_meters';

    public const KEY_IMPERSONATION_ENABLED = 'impersonation_enabled';

    public function get(string $key, ?string $default = null): ?string
    {
        return Setting::query()->where('key', $key)->value('value') ?? $default;
    }

    public function set(string $key, ?string $value): void
    {
        Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }

    public function boolean(string $key, bool $default = false): bool
    {
        $value = $this->get($key);
        if ($value === null || $value === '') {
            return $default;
        }

        return $value === '1' || strtolower($value) === 'true';
    }

    /**
     * Generation defaults from config/payroll.php (not editable in Settings UI).
     */
    public function multiplier(string $name): float
    {
        return (float) config('payroll.multipliers.'.$name, 1.0);
    }
}
