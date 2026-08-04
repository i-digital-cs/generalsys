<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GeneratePayrollRequest;
use App\Http\Requests\Admin\StorePayrollAdjustmentRequest;
use App\Http\Requests\Admin\UpdatePayrollDraftRequest;
use App\Models\PayrollAdjustment;
use App\Models\PayrollRecord;
use App\Services\Payroll\PayrollDraftService;
use App\Services\Payroll\PayrollGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin payroll drafts (T-4.4 / T-4.5) — list, generate, review, edit, approve.
 */
class PayrollController extends Controller
{
    public function __construct(
        private readonly PayrollGenerator $generator,
        private readonly PayrollDraftService $drafts,
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

        return view('admin.payroll.index', [
            'records' => $query->paginate(25)->withQueryString(),
            'month' => $month,
            'year' => $year,
            'status' => $status,
        ]);
    }

    public function show(PayrollRecord $payroll): View
    {
        $this->authorize('view', $payroll);

        $payroll->load(['user', 'adjustments']);

        return view('admin.payroll.show', [
            'payroll' => $payroll,
            'editable' => $payroll->status === PayrollRecord::STATUS_DRAFT,
        ]);
    }

    public function update(UpdatePayrollDraftRequest $request, PayrollRecord $payroll): RedirectResponse
    {
        $this->drafts->updateCounts($payroll, $request->validated());

        return redirect()
            ->route('admin.payroll.show', $payroll)
            ->with('success', __('payroll.flash.updated'));
    }

    public function storeAdjustment(StorePayrollAdjustmentRequest $request, PayrollRecord $payroll): RedirectResponse
    {
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
        $jobs = $this->generator->queueGeneration(
            (int) $data['month'],
            (int) $data['year'],
            $request->user(),
            isset($data['team_leader_id']) ? (int) $data['team_leader_id'] : null,
        );

        return redirect()
            ->route('admin.payroll.index', [
                'month' => $data['month'],
                'year' => $data['year'],
            ])
            ->with('success', __('payroll.flash.queued', ['jobs' => $jobs]));
    }

    public function approve(PayrollRecord $payroll): RedirectResponse
    {
        $this->authorize('approve', $payroll);

        $this->generator->approve($payroll);

        return redirect()
            ->route('admin.payroll.show', $payroll)
            ->with('success', __('payroll.flash.approved'));
    }
}
