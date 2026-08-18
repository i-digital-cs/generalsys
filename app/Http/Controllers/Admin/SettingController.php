<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSettingsRequest;
use App\Services\Admin\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function __construct(private readonly SettingService $settings) {}

    public function edit(): View
    {
        return view('admin.settings.edit', [
            'defaultLanguage' => $this->settings->get(SettingService::KEY_DEFAULT_LANGUAGE, 'ar'),
            'defaultCurrency' => $this->settings->get(SettingService::KEY_DEFAULT_CURRENCY, 'EGP'),
            'officeLatitude' => $this->settings->get(SettingService::KEY_OFFICE_LATITUDE, ''),
            'officeLongitude' => $this->settings->get(SettingService::KEY_OFFICE_LONGITUDE, ''),
            'officeRadiusMeters' => $this->settings->get(
                SettingService::KEY_OFFICE_RADIUS_METERS,
                (string) config('attendance.location.radius_meters', 1000),
            ),
            'impersonationEnabled' => $this->settings->boolean(SettingService::KEY_IMPERSONATION_ENABLED),
            'multipliers' => $this->settings->multipliers(),
        ]);
    }

    public function update(UpdateSettingsRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $this->settings->set(SettingService::KEY_DEFAULT_LANGUAGE, $validated['default_language']);
        $this->settings->set(SettingService::KEY_DEFAULT_CURRENCY, $validated['default_currency']);
        $this->settings->set(SettingService::KEY_OFFICE_LATITUDE, $validated['office_latitude'] ?? null);
        $this->settings->set(SettingService::KEY_OFFICE_LONGITUDE, $validated['office_longitude'] ?? null);
        $this->settings->set(
            SettingService::KEY_OFFICE_RADIUS_METERS,
            isset($validated['office_radius_meters'])
                ? (string) (int) $validated['office_radius_meters']
                : (string) config('attendance.location.radius_meters', 1000),
        );
        $this->settings->setMultipliers($validated['multipliers']);
        $this->settings->set(
            SettingService::KEY_IMPERSONATION_ENABLED,
            ! empty($validated['impersonation_enabled']) ? '1' : '0',
        );

        return redirect()
            ->route('admin.settings')
            ->with('success', __('settings.flash.updated'));
    }
}
