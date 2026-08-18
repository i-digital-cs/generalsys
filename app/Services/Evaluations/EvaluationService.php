<?php

namespace App\Services\Evaluations;

use App\Exceptions\EvaluationException;
use App\Models\Attendance;
use App\Models\Evaluation;
use App\Models\User;
use App\Services\Employees\TeamMembershipService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Monthly evaluation from per-TL daily scores (T-3.2 / T-8.5).
 *
 * - Four TL metrics = AVG of that evaluator's attendance_daily_scores.
 * - Attendance % = AVG of attendance.attendance_score (system).
 * - App total = average of 5. Legacy Excel four-metric fixture 0.97375.
 */
class EvaluationService
{
    public function __construct(
        private readonly DailyScoreService $dailyScores,
        private readonly TeamMembershipService $membership,
    ) {}

    /**
     * @return array{
     *     performance_score: float,
     *     behavior_score: float,
     *     productivity_score: float,
     *     interest_return_score: float,
     *     attendance_score: float,
     *     scores_sum: float,
     *     total_score: float,
     *     legacy_four_average_0_1: float
     * }
     */
    public function monthlyFromDaily(User $user, int $month, int $year, ?int $evaluatorId = null): array
    {
        $this->assertMonthYear($month, $year);

        $from = Carbon::create($year, $month, 1)->startOfDay();
        $to = $from->copy()->endOfMonth();

        $four = $this->dailyScores->monthAverages($user, $month, $year, $evaluatorId);

        $attendanceAvg = Attendance::query()
            ->where('user_id', $user->id)
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->toDateString())
            ->avg('attendance_score');

        $performance = $four['performance_score'];
        $behavior = $four['behavior_score'];
        $productivity = $four['productivity_score'];
        $interestReturn = $four['interest_return_score'];
        $attendance = $this->asScore($attendanceAvg);

        return [
            'performance_score' => $performance,
            'behavior_score' => $behavior,
            'productivity_score' => $productivity,
            'interest_return_score' => $interestReturn,
            'attendance_score' => $attendance,
            'scores_sum' => round(
                $performance + $behavior + $productivity + $interestReturn + $attendance,
                2,
            ),
            'total_score' => $this->totalFromFive(
                $performance,
                $behavior,
                $productivity,
                $interestReturn,
                $attendance,
            ),
            'legacy_four_average_0_1' => $this->legacyFourAverageAsFraction(
                $performance,
                $behavior,
                $productivity,
                $attendance,
            ),
        ];
    }

    public function totalFromFive(
        float $performance,
        float $behavior,
        float $productivity,
        float $interestReturn,
        float $attendance,
    ): float {
        return round(($performance + $behavior + $productivity + $interestReturn + $attendance) / 5, 2);
    }

    public function legacyFourAverageAsFraction(
        float $performance,
        float $behavior,
        float $productivity,
        float $attendance,
    ): float {
        $avgPercent = ($performance + $behavior + $productivity + $attendance) / 4;

        return round($avgPercent / 100, 5);
    }

    public function saveMonthly(
        User $actor,
        User $employee,
        int $month,
        int $year,
        ?string $evaluationText = null,
    ): Evaluation {
        if (! $this->actorMayEvaluate($actor, $employee)) {
            throw EvaluationException::notTeamMember();
        }

        $scores = $this->monthlyFromDaily($employee, $month, $year, (int) $actor->id);

        return DB::transaction(function () use ($actor, $employee, $month, $year, $evaluationText, $scores) {
            $evaluation = Evaluation::query()->updateOrCreate(
                [
                    'user_id' => $employee->id,
                    'team_leader_id' => $actor->id,
                    'month' => $month,
                    'year' => $year,
                ],
                [
                    'performance_score' => $scores['performance_score'],
                    'behavior_score' => $scores['behavior_score'],
                    'productivity_score' => $scores['productivity_score'],
                    'interest_return_score' => $scores['interest_return_score'],
                    'attendance_score' => $scores['attendance_score'],
                    'total_score' => $scores['total_score'],
                    'evaluation_text' => $evaluationText,
                ],
            );

            return $evaluation->refresh();
        });
    }

    public function actorMayEvaluate(User $actor, User $employee): bool
    {
        if ($actor->isManager()) {
            return true;
        }

        if (! $actor->isTeamLeader()) {
            return false;
        }

        return $employee->isEmployee() && $this->membership->leads($actor, $employee);
    }

    /** @return Collection<int, User> */
    public function evaluableEmployees(User $actor): Collection
    {
        if ($actor->isManager()) {
            return User::query()
                ->whereIn('role', [User::ROLE_EMPLOYEE, User::ROLE_TEAM_LEADER])
                ->where('status', User::STATUS_ACTIVE)
                ->orderBy('name_en')
                ->get();
        }

        if ($actor->isTeamLeader()) {
            return User::query()
                ->where('role', User::ROLE_EMPLOYEE)
                ->where('status', User::STATUS_ACTIVE)
                ->ledBy($actor->id)
                ->orderBy('name_en')
                ->get();
        }

        return collect();
    }

    /**
     * @return array{
     *     scores: array<string, float>,
     *     evaluation: ?Evaluation,
     *     evaluation_text: ?string,
     *     others: Collection<int, Evaluation>
     * }
     */
    public function snapshot(User $employee, int $month, int $year, ?User $actor = null): array
    {
        $evaluatorId = $actor?->id;
        $scores = $this->monthlyFromDaily($employee, $month, $year, $evaluatorId);
        $rows = $this->evaluationsForUserMonth($employee, $month, $year);
        $own = $evaluatorId === null
            ? $rows->first()
            : $rows->first(fn (Evaluation $row) => (int) $row->team_leader_id === (int) $evaluatorId);

        return [
            'scores' => $scores,
            'evaluation' => $own,
            'evaluation_text' => $own?->evaluation_text,
            'others' => $own === null
                ? $rows
                : $rows->reject(fn (Evaluation $row) => (int) $row->id === (int) $own->id)->values(),
        ];
    }

    /** @return Collection<int, Evaluation> */
    public function listForUser(User $user, ?int $year = null): Collection
    {
        $query = Evaluation::query()
            ->with('teamLeader')
            ->where('user_id', $user->id)
            ->orderByDesc('year')
            ->orderByDesc('month');

        if ($year !== null) {
            $query->where('year', $year);
        }

        return $query->get();
    }

    /** @return Collection<int, Evaluation> */
    public function evaluationsForUserMonth(User $user, int $month, int $year): Collection
    {
        $this->assertMonthYear($month, $year);

        return Evaluation::query()
            ->with('teamLeader')
            ->where('user_id', $user->id)
            ->where('month', $month)
            ->where('year', $year)
            ->orderBy('id')
            ->get();
    }

    /**
     * Combined payslip block: average of saved TL totals for the month.
     *
     * @return array{
     *     scores: array<string, float>,
     *     evaluation_text: ?string,
     *     monthly_totals: array<int, float|null>,
     *     year_average: ?float,
     *     is_saved: bool,
     *     evaluated_by_id: ?int
     * }
     */
    public function summaryForPayslip(User $user, int $month, int $year): array
    {
        $this->assertMonthYear($month, $year);

        $yearRows = Evaluation::query()
            ->with('teamLeader')
            ->where('user_id', $user->id)
            ->where('year', $year)
            ->get()
            ->groupBy('month');

        $saved = $yearRows->get($month, collect());
        $scores = $saved->isNotEmpty()
            ? $this->averageEvaluationScores($saved)
            : $this->monthlyFromDaily($user, $month, $year);

        $monthlyTotals = [];
        for ($m = 1; $m <= 12; $m++) {
            $group = $yearRows->get($m, collect());
            if ($group->isNotEmpty()) {
                $monthlyTotals[$m] = round((float) $group->avg('total_score'), 2);
            } elseif ($m === $month) {
                $monthlyTotals[$m] = $scores['total_score'];
            } else {
                $monthlyTotals[$m] = null;
            }
        }

        $present = array_values(array_filter(
            $monthlyTotals,
            static fn (?float $v) => $v !== null,
        ));

        $texts = $saved
            ->filter(fn (Evaluation $row) => filled($row->evaluation_text))
            ->map(function (Evaluation $row): string {
                $name = $row->teamLeader?->localizedName();
                $body = (string) $row->evaluation_text;

                return $name ? $name.': '.$body : $body;
            })
            ->values()
            ->all();

        return [
            'scores' => $scores,
            'evaluation_text' => $texts === [] ? null : implode("\n\n", $texts),
            'monthly_totals' => $monthlyTotals,
            'year_average' => $present === []
                ? null
                : round(array_sum($present) / count($present), 2),
            'is_saved' => $saved->isNotEmpty(),
            'evaluated_by_id' => $saved->count() === 1 ? $saved->first()?->team_leader_id : null,
        ];
    }

    /** @param  Collection<int, Evaluation>  $rows */
    private function averageEvaluationScores(Collection $rows): array
    {
        $performance = $this->asScore($rows->avg('performance_score'));
        $behavior = $this->asScore($rows->avg('behavior_score'));
        $productivity = $this->asScore($rows->avg('productivity_score'));
        $interestReturn = $this->asScore($rows->avg('interest_return_score'));
        $attendance = $this->asScore($rows->avg('attendance_score'));

        return [
            'performance_score' => $performance,
            'behavior_score' => $behavior,
            'productivity_score' => $productivity,
            'interest_return_score' => $interestReturn,
            'attendance_score' => $attendance,
            'scores_sum' => round($performance + $behavior + $productivity + $interestReturn + $attendance, 2),
            'total_score' => $this->totalFromFive(
                $performance,
                $behavior,
                $productivity,
                $interestReturn,
                $attendance,
            ),
            'legacy_four_average_0_1' => $this->legacyFourAverageAsFraction(
                $performance,
                $behavior,
                $productivity,
                $attendance,
            ),
        ];
    }

    private function assertMonthYear(int $month, int $year): void
    {
        if ($month < 1 || $month > 12 || $year < 2000 || $year > 2100) {
            throw EvaluationException::invalidMonth();
        }
    }

    private function asScore(mixed $value): float
    {
        if ($value === null) {
            return 0.0;
        }

        return round((float) $value, 2);
    }
}
