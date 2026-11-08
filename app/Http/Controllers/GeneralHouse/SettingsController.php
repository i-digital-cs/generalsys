<?php

namespace App\Http\Controllers\GeneralHouse;

use App\Http\Controllers\Controller;
use App\Http\Requests\GeneralHouse\UpdateGhVatSettingRequest;
use App\Services\GeneralHouse\Settings\GhVatConfigService;
use App\Support\GeneralHouse\GhCatalogAccess;
use App\Support\GeneralHouse\GhLaunchConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * General House tax / minimum-order configuration for managers.
 * VAT rate is DB-backed (settings key) with env/config fallback — not editable on invoices.
 */
class SettingsController extends Controller
{
    public function __construct(
        private readonly GhVatConfigService $vatConfig,
    ) {}

    public function index(Request $request): View
    {
        abort_unless(GhCatalogAccess::mayManage($request->user()), 403);

        return view('gh.settings.index', [
            'standardVatRate' => $this->vatConfig->standardRate(),
            'vatFromDatabase' => $this->vatConfig->hasDatabaseOverride(),
            'vatEnvFallback' => (string) config('general_house.standard_vat_rate', '5.00'),
            'defaultOfferingTaxClass' => (string) config('general_house.default_offering_tax_class', 'STANDARD_RATED'),
            'defaultMinOrderPolicy' => GhLaunchConfig::defaultMinOrderPolicy(),
            'surchargeEnabled' => GhLaunchConfig::minOrderSurchargeEnabled(),
            'surchargeTaxClass' => config('general_house.min_order_surcharge_tax_class'),
            'vatAudits' => $this->vatConfig->recentAudits(8),
        ]);
    }

    public function updateVat(UpdateGhVatSettingRequest $request): RedirectResponse
    {
        $this->vatConfig->updateStandardRate(
            $request->user(),
            (string) $request->validated('standard_vat_rate'),
            (string) $request->validated('reason'),
        );

        return redirect()
            ->route('gh.settings')
            ->with('success', __('gh.settings.vat_updated'));
    }
}
