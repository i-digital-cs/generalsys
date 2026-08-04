<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Advance;
use App\Services\Payroll\AdvanceService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Employee own advances + remaining (T-4.8 / API §9).
 */
class AdvanceController extends Controller
{
    public function __construct(private readonly AdvanceService $advances) {}

    public function index(Request $request): View
    {
        $this->authorize('viewOwn', Advance::class);

        return view('employee.advances.index', [
            'advances' => $this->advances->listForEmployee($request->user()),
        ]);
    }
}
