<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\DashboardStatsService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardStatsService $stats) {}

    public function index(): View
    {
        return view('admin.dashboard', [
            'stats' => $this->stats->stats(),
            'expiringContracts' => $this->stats->expiringContracts(),
        ]);
    }
}
