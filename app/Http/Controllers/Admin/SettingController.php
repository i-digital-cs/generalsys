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
            'multipliers' => $this->settings->multipliers(),
        ]);
    }

    public function update(UpdateSettingsRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $this->settings->set(SettingService::KEY_DEFAULT_LANGUAGE, $validated['default_language']);
        $this->settings->set(SettingService::KEY_DEFAULT_CURRENCY, $validated['default_currency']);
        $this->settings->setMultipliers($validated['multipliers']);

        return redirect()
            ->route('admin.settings')
            ->with('success', __('settings.flash.updated'));
    }
}
