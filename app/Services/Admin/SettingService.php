<?php

namespace App\Services\Admin;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Global key/value settings (schema doc §19).
 * Payroll multipliers are data (AGENTS.md / PAYROLL_CALCULATION §8), not code branches.
 */
class SettingService
{
    public const KEY_DEFAULT_LANGUAGE = 'default_language';

    public const KEY_DEFAULT_CURRENCY = 'default_currency';

    public const KEY_OFFICE_LATITUDE = 'office_latitude';

    public const KEY_OFFICE_LONGITUDE = 'office_longitude';

    public const KEY_OFFICE_RADIUS_METERS = 'office_radius_meters';

    public const CACHE_MULTIPLIERS = 'settings.payroll_multipliers';

    /** @var list<string> */
    public const MULTIPLIER_NAMES = [
        'friday_full_time',
        'friday_online',
        'day_off_full_time',
        'day_off_online',
        'overtime',
        'official_holiday_work',
        'extra_pay',
    ];

    public function get(string $key, ?string $default = null): ?string
    {
        return Setting::query()->where('key', $key)->value('value') ?? $default;
    }

    public function set(string $key, ?string $value): void
    {
        Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }

    public function multiplier(string $name): float
    {
        $all = $this->multipliers();

        return (float) ($all[$name] ?? config('payroll.multipliers.'.$name, 1.0));
    }

    /**
     * @return array<string, float>
     */
    public function multipliers(): array
    {
        return Cache::remember(self::CACHE_MULTIPLIERS, now()->addDay(), function (): array {
            $out = [];
            foreach (self::MULTIPLIER_NAMES as $name) {
                $default = (float) config('payroll.multipliers.'.$name, 1.0);
                $stored = $this->get($this->multiplierKey($name));
                $out[$name] = $stored !== null && $stored !== ''
                    ? (float) $stored
                    : $default;
            }

            return $out;
        });
    }

    /**
     * @param  array<string, float|int|string>  $values
     */
    public function setMultipliers(array $values): void
    {
        foreach (self::MULTIPLIER_NAMES as $name) {
            if (! array_key_exists($name, $values)) {
                continue;
            }
            $this->set($this->multiplierKey($name), number_format((float) $values[$name], 4, '.', ''));
        }

        Cache::forget(self::CACHE_MULTIPLIERS);
    }

    public function multiplierKey(string $name): string
    {
        return 'payroll.multiplier.'.$name;
    }
}
