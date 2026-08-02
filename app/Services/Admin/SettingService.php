<?php

namespace App\Services\Admin;

use App\Models\Setting;

/**
 * Global key/value settings (schema doc §19).
 */
class SettingService
{
    public const KEY_DEFAULT_LANGUAGE = 'default_language';

    public const KEY_DEFAULT_CURRENCY = 'default_currency';

    public function get(string $key, ?string $default = null): ?string
    {
        return Setting::where('key', $key)->value('value') ?? $default;
    }

    public function set(string $key, ?string $value): void
    {
        Setting::updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
