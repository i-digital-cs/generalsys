<?php

namespace App\Http\Controllers\GeneralHouse;

use App\Http\Controllers\Controller;
use App\Http\Requests\GeneralHouse\UpdateGhAppVersionPolicyRequest;
use App\Models\GeneralHouse\GhAppVersionPolicy;
use App\Models\GeneralHouse\GhAppVersionPolicyAudit;
use App\Services\GeneralHouse\App\GhAppVersionPolicyService;
use App\Support\GeneralHouse\GhAppChannel;
use App\Support\GeneralHouse\GhAppPlatform;
use App\Support\GnrlAppAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Global App Version Management for GNRL Operations (Manager-only).
 */
class AppVersionPolicyController extends Controller
{
    public function __construct(
        private readonly GhAppVersionPolicyService $policies,
    ) {}

    public function index(Request $request): View
    {
        abort_unless(GnrlAppAccess::mayManageVersions($request->user()), 403);

        $policies = $this->policies->allPolicies()->groupBy(fn (GhAppVersionPolicy $p) => $p->platform);

        $audits = GhAppVersionPolicyAudit::query()
            ->with(['actor', 'policy'])
            ->orderByDesc('recorded_at')
            ->limit(25)
            ->get();

        return view('admin.app.versions', [
            'policiesByPlatform' => $policies,
            'platforms' => GhAppPlatform::all(),
            'channels' => GhAppChannel::all(),
            'trustedChannel' => $this->policies->trustedChannel(),
            'audits' => $audits,
        ]);
    }

    public function update(UpdateGhAppVersionPolicyRequest $request, GhAppVersionPolicy $policy): RedirectResponse
    {
        $this->policies->updatePolicy(
            $request->user(),
            $policy,
            $request->validated(),
            $request->validated('reason')
        );

        return redirect()
            ->route('admin.app.versions')
            ->with('success', __('gh.app_versions.flash.updated', [
                'platform' => $policy->platform,
                'channel' => $policy->channel,
            ]));
    }
}
