<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function __construct(private readonly SettingService $settings) {}

    public function edit(): View
    {
        return view('admin.settings.edit', [
            'defaultLanguage' => $this->settings->get(SettingService::KEY_DEFAULT_LANGUAGE, 'ar'),
            'defaultCurrency' => $this->settings->get(SettingService::KEY_DEFAULT_CURRENCY, 'EGP'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'default_language' => ['required', Rule::in(['ar', 'en'])],
            'default_currency' => ['required', Rule::in(['EGP', 'AED'])],
        ]);

        $this->settings->set(SettingService::KEY_DEFAULT_LANGUAGE, $validated['default_language']);
        $this->settings->set(SettingService::KEY_DEFAULT_CURRENCY, $validated['default_currency']);

        return redirect()
            ->route('admin.settings')
            ->with('success', __('settings.flash.updated'));
    }
}
