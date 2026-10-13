<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GeneratePayrollRequest;
use App\Http\Requests\Admin\StorePayrollAdjustmentRequest;
use App\Http\Requests\Admin\UpdatePayrollDraftRequest;
use App\Jobs\ExportPayrollExcelJob;
use App\Models\PayrollAdjustment;
use App\Models\PayrollRecord;
use App\Services\Attendance\HoursComparisonService;
use App\Services\Evaluations\EvaluationService;
use App\Services\Payroll\PayrollAggregator;
use App\Services\Payroll\PayrollDraftService;
use App\Services\Payroll\PayrollExportService;
use App\Services\Payroll\PayrollGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin payroll drafts (T-4.4 / T-4.5 / T-5.2) — list, generate, review, export.
 */
class PayrollController extends Controller
{
    public function __construct(
        private readonly PayrollGenerator $generator,
        private readonly PayrollDraftService $drafts,
        private readonly PayrollExportService $exports,
        private readonly EvaluationService $evaluations,
        private readonly PayrollAggregator $aggregator,
        private readonly HoursComparisonService $hours,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', PayrollRecord::class);

        $month = max(1, min(12, (int) $request->input('month', now()->month)));
        $year = max(2020, min(2100, (int) $request->input('year', now()->year)));
        $status = $request->input('status');

        $query = PayrollRecord::query()
            ->with('user')
            ->where('month', $month)
            ->where('year', $year)
            ->orderBy('id');

        if (in_array($status, [
            PayrollRecord::STATUS_DRAFT,
            PayrollRecord::STATUS_APPROVED,
            PayrollRecord::STATUS_PAID,
        ], true)) {
            $query->where('status', $status);
        }

        $exportPath = Cache::get(ExportPayrollExcelJob::cacheKey(
            (int) $request->user()->id,
            $month,
            $year,
        ));

        return view('admin.payroll.index', [
            'records' => $query->paginate(25)->withQueryString(),
            'month' => $month,
            'year' => $year,
            'status' => $status,
            'exportReady' => is_string($exportPath) && $exportPath !== '',
        ]);
    }

    public function show(PayrollRecord $payroll): View
    {
        $this->authorize('view', $payroll);

        // Drafts re-pull ACTIVE advances only. Attendance day counts stay as saved
        // so Admin can edit inputs; use "Refresh from attendance" or Generate to re-pull.
        if ($payroll->status === PayrollRecord::STATUS_DRAFT) {
            $payroll = $this->drafts->recalculate($payroll, false);
        }

        $payroll->load(['user.teamLeaders', 'adjustments']);

        $scores = null;
        $evaluationText = null;
        $evaluationSaved = false;
        $monthlyTotals = null;
        $yearAverage = null;
        $attendanceSummary = null;
        $hoursMonth = null;
        $liveCounts = null;
        $countsOutOfSync = false;
        $suggestedLateMinutes = null;

        if ($payroll->user !== null) {
            $eval = $this->evaluations->summaryForPayslip(
                $payroll->user,
                (int) $payroll->month,
                (int) $payroll->year,
            );
            $scores = $eval['scores'];
            $evaluationText = $eval['evaluation_text'];
            $monthlyTotals = $eval['monthly_totals'];
            $yearAverage = $eval['year_average'];
            $evaluationSaved = (bool) ($eval['is_saved'] ?? false);
            $attendanceSummary = $this->aggregator->monthStatusSummary(
                $payroll->user,
                (int) $payroll->month,
                (int) $payroll->year,
            );
            $hoursMonth = $this->hours->monthForUser(
                $payroll->user,
                sprintf('%04d-%02d', $payroll->year, $payroll->month),
            );

            if ($payroll->status === PayrollRecord::STATUS_DRAFT) {
                $live = $this->aggregator->aggregate(
                    $payroll->user,
                    (int) $payroll->month,
                    (int) $payroll->year,
                );
                // Suggestion = punch lateness (pre-grace). Applied totals still use date-aware exactLateMinutes.
                $suggestedLateMinutes = (int) $live['suggested_late_minutes_total'];
                $liveCounts = [
                    'full_time_days' => (int) $live['full_time_days'],
                    'online_half_days' => (int) $live['online_half_days'],
                    'online_full_days' => (int) $live['online_full_days'],
                    'rest_days' => (int) $live['rest_days'],
                    'hours_deduction' => (float) $live['hours_deduction'],
                    'late_minutes_total' => (int) $live['late_minutes_total'],
                ];
                $countsOutOfSync = (int) $payroll->full_time_days !== $liveCounts['full_time_days']
                    || (int) $payroll->online_half_days !== $liveCounts['online_half_days']
                    || (int) $payroll->online_full_days !== $liveCounts['online_full_days']
                    || (int) $payroll->rest_days !== $liveCounts['rest_days']
                    || abs((float) $payroll->hours_deduction - $liveCounts['hours_deduction']) >= 0.01;
            }
        }

        return view('admin.payroll.show', [
            'payroll' => $payroll,
            'editable' => $payroll->status === PayrollRecord::STATUS_DRAFT,
            'scores' => $scores,
            'evaluationText' => $evaluationText,
            'evaluationSaved' => $evaluationSaved ?? false,
            'monthlyTotals' => $monthlyTotals,
            'yearAverage' => $yearAverage,
            'attendanceSummary' => $attendanceSummary,
            'hoursMonth' => $hoursMonth,
            'liveCounts' => $liveCounts,
            'countsOutOfSync' => $countsOutOfSync,
            'suggestedLateMinutes' => $suggestedLateMinutes,
        ]);
    }

    public function update(UpdatePayrollDraftRequest $request, PayrollRecord $payroll): RedirectResponse
    {
        $this->authorize('update', $payroll);

        $this->drafts->updateCounts($payroll, $request->validated());

        return redirect()
            ->route('admin.payroll.show', $payroll)
            ->with('success', __('payroll.flash.updated'));
    }

    public function refreshAttendance(PayrollRecord $payroll): RedirectResponse
    {
        $this->authorize('update', $payroll);

        $this->drafts->refreshFromAttendance($payroll);

        return redirect()
            ->route('admin.payroll.show', $payroll)
            ->with('success', __('payroll.flash.refreshed_attendance'));
    }

    public function storeAdjustment(StorePayrollAdjustmentRequest $request, PayrollRecord $payroll): RedirectResponse
    {
        $this->authorize('update', $payroll);

        $this->drafts->addAdjustment($payroll, $request->validated(), $request->user());

        return redirect()
            ->route('admin.payroll.show', $payroll)
            ->with('success', __('payroll.flash.adjustment_added'));
    }

    public function destroyAdjustment(PayrollRecord $payroll, PayrollAdjustment $adjustment): RedirectResponse
    {
        $this->authorize('update', $payroll);

        $this->drafts->deleteAdjustment($payroll, $adjustment);

        return redirect()
            ->route('admin.payroll.show', $payroll)
            ->with('success', __('payroll.flash.adjustment_removed'));
    }

    public function generate(GeneratePayrollRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $month = (int) $data['month'];
        $year = (int) $data['year'];
        $teamLeaderId = isset($data['team_leader_id']) ? (int) $data['team_leader_id'] : null;

        $redirect = redirect()->route('admin.payroll.index', [
            'month' => $month,
            'year' => $year,
        ]);

        if (config('payroll.generate_inline', true)) {
            $report = $this->generator->generateMonthInline(
                $month,
                $year,
                $request->user(),
                $teamLeaderId,
            );

            $success = __('payroll.flash.generated', [
                'created' => $report['created'],
                'skipped' => count($report['skipped']),
            ]);

            $error = null;
            $warnings = $report['incomplete'] !== [] ? $report['incomplete'] : $report['skipped'];
            if ($warnings !== []) {
                $details = collect($warnings)
                    ->map(fn (array $row) => __('payroll.flash.skipped_line', [
                        'name' => $row['name'],
                        'classified' => $row['classified'],
                        'expected' => $row['expected'],
                    ]))
                    ->implode(' · ');

                $hint = $report['skipped'] !== []
                    ? __('payroll.flash.coverage_hint')
                    : __('payroll.flash.coverage_warning');

                $error = $hint.' '.$details;
            }

            if ($report['created'] === 1 && $report['first_id'] !== null) {
                $toShow = redirect()
                    ->route('admin.payroll.show', $report['first_id'])
                    ->with('success', $success);

                return $error !== null ? $toShow->with('error', $error) : $toShow;
            }

            $redirect->with('success', $success);

            return $error !== null ? $redirect->with('error', $error) : $redirect;
        }

        $jobs = $this->generator->queueGeneration(
            $month,
            $year,
            $request->user(),
            $teamLeaderId,
        );

        return $redirect->with('success', __('payroll.flash.queued', ['jobs' => $jobs]));
    }

    public function approve(PayrollRecord $payroll): RedirectResponse
    {
        $this->authorize('approve', $payroll);

        $this->generator->approve($payroll);

        return redirect()
            ->route('admin.payroll.show', $payroll)
            ->with('success', __('payroll.flash.approved'));
    }

    public function exportPdf(PayrollRecord $payroll): Response
    {
        $this->authorize('view', $payroll);

        $filename = sprintf('payslip-%d-%02d-%04d.pdf', $payroll->id, $payroll->month, $payroll->year);

        return $this->exports->downloadPayslipPdf($payroll)->download($filename);
    }

    public function exportReviewPdf(PayrollRecord $payroll): Response
    {
        $this->authorize('view', $payroll);

        $filename = sprintf('payroll-review-%d-%02d-%04d.pdf', $payroll->id, $payroll->month, $payroll->year);

        return $this->exports->downloadReviewPdf($payroll)->download($filename);
    }

    public function queueExcelExport(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', PayrollRecord::class);

        $month = max(1, min(12, (int) $request->input('month', now()->month)));
        $year = max(2020, min(2100, (int) $request->input('year', now()->year)));

        ExportPayrollExcelJob::dispatch($month, $year, (int) $request->user()->id);

        return redirect()
            ->route('admin.payroll.index', ['month' => $month, 'year' => $year])
            ->with('success', __('payroll.flash.excel_queued'));
    }

    public function downloadExcel(Request $request): BinaryFileResponse|StreamedResponse|RedirectResponse
    {
        $this->authorize('viewAny', PayrollRecord::class);

        $month = max(1, min(12, (int) $request->input('month', now()->month)));
        $year = max(2020, min(2100, (int) $request->input('year', now()->year)));

        $path = Cache::get(ExportPayrollExcelJob::cacheKey(
            (int) $request->user()->id,
            $month,
            $year,
        ));

        if (! is_string($path) || $path === '') {
            return redirect()
                ->route('admin.payroll.index', ['month' => $month, 'year' => $year])
                ->with('error', __('payroll.flash.excel_not_ready'));
        }

        $name = sprintf('payroll-%04d-%02d.xlsx', $year, $month);

        return $this->exports->streamStoredExcel($path, $name);
    }
}
