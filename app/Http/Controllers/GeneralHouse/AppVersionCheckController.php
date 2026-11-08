<?php

namespace App\Http\Controllers\GeneralHouse;

use App\Http\Controllers\Controller;
use App\Services\GeneralHouse\App\GhAppVersionPolicyService;
use App\Support\GeneralHouse\GhAppPlatform;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Read-only version policy check for Capacitor / future iOS clients.
 * Channel is always the trusted server channel — client channel cannot select policy.
 */
class AppVersionCheckController extends Controller
{
    public function __construct(
        private readonly GhAppVersionPolicyService $policies,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $data = $request->validate([
                'platform' => ['required', 'string', Rule::in(GhAppPlatform::all())],
                'build_number' => ['required', 'integer', 'min:0', 'max:2147483647'],
                'version_name' => ['nullable', 'string', 'max:50'],
                'app_channel' => ['nullable', 'string', 'max:16'],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'service_ok' => false,
                'error' => 'invalid_input',
                'messages' => $e->errors(),
            ], 422);
        }

        try {
            $result = $this->policies->evaluateCheck(
                (string) $data['platform'],
                (int) $data['build_number'],
                app()->getLocale(),
                isset($data['app_channel']) ? strtolower((string) $data['app_channel']) : null,
            );
        } catch (ValidationException $e) {
            return response()->json([
                'service_ok' => false,
                'error' => 'policy_rejected',
                'messages' => $e->errors(),
            ], 422);
        }

        return response()->json([
            'latest_version' => $result['latest_version'],
            'latest_build' => $result['latest_build'],
            'minimum_build' => $result['minimum_build'],
            'update_available' => $result['update_available'],
            'update_required' => $result['update_required'],
            'update_url' => $result['update_url'],
            'release_notes' => $result['release_notes'],
            'channel' => $result['channel'],
            'platform' => $result['platform'],
            'service_ok' => true,
        ]);
    }
}
