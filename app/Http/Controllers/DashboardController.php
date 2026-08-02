<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

/**
 * Generic authenticated landing page (TEAM_LEADER until the Phase-2
 * team dashboard ships; MANAGER/EMPLOYEE are redirected elsewhere).
 */
class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('dashboard');
    }
}
