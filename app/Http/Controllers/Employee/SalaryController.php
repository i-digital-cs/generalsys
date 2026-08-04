<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\PayrollRecord;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * My Salary (T-4.7) — own APPROVED/PAID payslips only; DRAFT never shown.
 */
class SalaryController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAnyOwn', PayrollRecord::class);

        $records = PayrollRecord::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('status', [
                PayrollRecord::STATUS_APPROVED,
                PayrollRecord::STATUS_PAID,
            ])
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->paginate(25);

        return view('employee.salary.index', [
            'records' => $records,
        ]);
    }

    public function show(PayrollRecord $payroll): View
    {
        $this->authorize('view', $payroll);

        $payroll->load('adjustments');

        return view('employee.salary.show', [
            'payroll' => $payroll,
        ]);
    }
}
