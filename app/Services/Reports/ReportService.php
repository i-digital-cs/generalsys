<?php

namespace App\Services\Reports;

use App\Models\Contract;
use App\Models\Evaluation;
use App\Models\PayrollAdjustment;
use App\Models\PayrollRecord;
use App\Models\User;
use App\Models\VacationBalance;
use Illuminate\Support\Collection;

/**
 * Annual employee report (T-5.1) — docs/PAYROLL_CALCULATION.md §6.4 / Gate 5.1.
 */
class ReportService
{
    /**
     * L33: AVG(total_score) for the year (null when no evaluations).
     */
    public function annualEvaluation(User $user, int $year): ?float
    {
        $monthAvgs = Evaluation::query()
            ->where('user_id', $user->id)
            ->where('year', $year)
            ->selectRaw('AVG(total_score) as month_avg')
            ->groupBy('month')
            ->pluck('month_avg');

        if ($monthAvgs->isEmpty()) {
            return null;
        }

        return round((float) $monthAvgs->avg(), 2);
    }

    /**
     * @return array{
     *     employee: User,
     *     year: int,
     *     contract: ?Contract,
     *     vacation: ?VacationBalance,
     *     evaluation_year_average: ?float,
     *     months: array<int, array{
     *         month: int,
     *         evaluation: ?float,
     *         penalties: float,
     *         bonuses: float,
     *         deductions: float,
     *         net_pay: ?float,
     *         gross_pay: ?float,
     *         payroll_status: ?string
     *     }>
     * }
     */
    public function annualReport(User $user, int $year): array
    {
        $evaluations = Evaluation::query()
            ->where('user_id', $user->id)
            ->where('year', $year)
            ->get()
            ->groupBy('month');

        $payrolls = PayrollRecord::query()
            ->with('adjustments')
            ->where('user_id', $user->id)
            ->where('year', $year)
            ->get()
            ->keyBy('month');

        $months = [];
        for ($month = 1; $month <= 12; $month++) {
            $evalGroup = $evaluations->get($month, collect());
            $pay = $payrolls->get($month);

            $months[$month] = [
                'month' => $month,
                'evaluation' => $evalGroup->isNotEmpty()
                    ? round((float) $evalGroup->avg('total_score'), 2)
                    : null,
                'penalties' => $pay !== null ? $this->penaltiesTotal($pay) : 0.0,
                'bonuses' => $pay !== null ? $this->bonusesTotal($pay) : 0.0,
                'deductions' => $pay !== null ? (float) $pay->total_deductions : 0.0,
                'net_pay' => $pay !== null ? (float) $pay->net_pay : null,
                'gross_pay' => $pay !== null ? (float) $pay->gross_pay : null,
                'payroll_status' => $pay?->status,
            ];
        }

        return [
            'employee' => $user,
            'year' => $year,
            'contract' => $user->activeContract ?? $user->contracts()->orderByDesc('contract_start')->first(),
            'vacation' => VacationBalance::query()
                ->where('user_id', $user->id)
                ->where('year', $year)
                ->first(),
            'evaluation_year_average' => $this->annualEvaluation($user, $year),
            'months' => $months,
        ];
    }

    /**
     * @return Collection<int, User>
     */
    public function eligibleEmployees(): Collection
    {
        return User::query()
            ->whereIn('role', [User::ROLE_EMPLOYEE, User::ROLE_TEAM_LEADER])
            ->where('status', User::STATUS_ACTIVE)
            ->orderBy('name_en')
            ->get();
    }

    private function penaltiesTotal(PayrollRecord $pay): float
    {
        return round(
            (float) $pay->late_penalty
            + (float) $pay->leave_permission_penalty
            + (float) $pay->late_permission_penalty
            + (float) $pay->excused_absence_penalty
            + (float) $pay->unexcused_absence_penalty
            + (float) $pay->forced_penalty_surcharge,
            2,
        );
    }

    private function bonusesTotal(PayrollRecord $pay): float
    {
        $fromAdjustments = $pay->adjustments
            ->where('type', PayrollAdjustment::TYPE_BONUS)
            ->sum(fn (PayrollAdjustment $row) => (float) $row->amount);

        return round(
            (float) $fromAdjustments
            + (float) $pay->friday_bonus
            + (float) $pay->day_off_bonus
            + (float) $pay->official_holiday_bonus,
            2,
        );
    }
}
