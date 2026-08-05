<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\PayrollRecord;
use App\Services\Evaluations\EvaluationService;
use App\Services\Payroll\PayrollAggregator;
use App\Services\Payroll\PayrollExportService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * My Salary (T-4.7 / T-5.2) — own APPROVED/PAID payslips + PDF.
 */
class SalaryController extends Controller
{
    public function __construct(
        private readonly PayrollExportService $exports,
        private readonly EvaluationService $evaluations,
        private readonly PayrollAggregator $aggregator,
    ) {}

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

        $payroll->load(['adjustments', 'user']);

        $scores = null;
        $evaluationText = null;
        $evaluationSaved = false;
        $monthlyTotals = null;
        $yearAverage = null;
        $attendanceSummary = null;

        if ($payroll->user !== null) {
            $eval = $this->evaluations->summaryForPayslip(
                $payroll->user,
                (int) $payroll->month,
                (int) $payroll->year,
            );
            $scores = $eval['scores'];
            $evaluationText = $eval['evaluation_text'];
            $evaluationSaved = (bool) ($eval['is_saved'] ?? false);
            $monthlyTotals = $eval['monthly_totals'];
            $yearAverage = $eval['year_average'];
            $attendanceSummary = $this->aggregator->monthStatusSummary(
                $payroll->user,
                (int) $payroll->month,
                (int) $payroll->year,
            );
        }

        return view('employee.salary.show', [
            'payroll' => $payroll,
            'scores' => $scores,
            'evaluationText' => $evaluationText,
            'evaluationSaved' => $evaluationSaved,
            'monthlyTotals' => $monthlyTotals,
            'yearAverage' => $yearAverage,
            'attendanceSummary' => $attendanceSummary,
        ]);
    }

    public function exportPdf(PayrollRecord $payroll): Response
    {
        $this->authorize('view', $payroll);

        $filename = sprintf('payslip-%d-%02d-%04d.pdf', $payroll->id, $payroll->month, $payroll->year);

        return $this->exports->downloadPayslipPdf($payroll)->download($filename);
    }
}
