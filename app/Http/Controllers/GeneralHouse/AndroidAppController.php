<?php

namespace App\Http\Controllers\GeneralHouse;

use App\Http\Controllers\Controller;
use App\Services\GeneralHouse\App\GhAndroidAppService;
use App\Support\GnrlAppAccess;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * GNRL Operations APK distribution for any active authenticated GeneralSys user.
 */
class AndroidAppController extends Controller
{
    public function __construct(
        private readonly GhAndroidAppService $androidApp,
    ) {}

    public function show(Request $request): View
    {
        abort_unless(GnrlAppAccess::mayDownload($request->user()), 403);
        abort_unless($this->androidApp->isAvailable(), 404);

        return view('gh.app.download', [
            'app' => $this->androidApp->metadata(),
        ]);
    }

    public function download(Request $request): BinaryFileResponse
    {
        abort_unless(GnrlAppAccess::mayDownload($request->user()), 403);
        abort_unless($this->androidApp->isAvailable(), 404);

        $payload = $this->androidApp->downloadPayload();

        return response()->download(
            $payload['path'],
            $payload['filename'],
            [
                'Content-Type' => $payload['mime'],
                'Content-Disposition' => 'attachment; filename="'.$payload['filename'].'"',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ]
        );
    }
}
