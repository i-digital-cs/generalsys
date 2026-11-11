<?php

namespace App\Http\Controllers\Ui;

use App\Http\Controllers\Controller;
use App\Support\Ui\ListViewMode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Persist the global Classic/Modern display preference for the authenticated user.
 */
class ListViewModeController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $mode = (string) $request->input('mode', ListViewMode::LEGACY);
        abort_unless(ListViewMode::isValidMode($mode), 422);

        $user = $request->user();
        abort_unless($user !== null, 401);

        ListViewMode::put($user, $mode);

        return redirect()->back();
    }
}
