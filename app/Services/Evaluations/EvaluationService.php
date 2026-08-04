<?php

namespace App\Services\Evaluations;

use App\Exceptions\EvaluationException;
use App\Models\Attendance;
use App\Models\Evaluation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Monthly evaluation from daily attendance scores (T-3.2).
 *
 * - Each metric = AVG of that month's non-null daily values.
 * - App total = average of 5 (incl. Interest Return).
 * - Legacy Excel total = average of 4 (excl. Interest Return) → fixture 0.97375 on 0–1 scale.
 */
class EvaluationService
{
    /**
     * @return array{
     *     performance_score: float,
     *     behavior_score: float,
     *     productivity_score: float,
     *     interest_return_score: float,
     *     attendance_score: float,
     *     total_score: float,
     *     legacy_four_average_0_1: float
     * }
     */
    public function monthlyFromDaily(User $user, int $month, int $year): array
    {
        $this->assertMonthYear($month, $year);

        $from = Carbon::create($year, $month, 1)->startOfDay();
        $to = $from->copy()->endOfMonth();

        $agg = Attendance::query()
            ->where('user_id', $user->id)
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->toDateString())
            ->selectRaw('AVG(performance_score) as performance_score')
            ->selectRaw('AVG(behavior_score) as behavior_score')
            ->selectRaw('AVG(productivity_score) as productivity_score')
            ->selectRaw('AVG(interest_return_score) as interest_return_score')
            ->selectRaw('AVG(attendance_score) as attendance_score')
            ->first();

        $performance = $this->asScore($agg?->performance_score);
        $behavior = $this->asScore($agg?->behavior_score);
        $productivity = $this->asScore($agg?->productivity_score);
        $interestReturn = $this->asScore($agg?->interest_return_score);
        $attendance = $this->asScore($agg?->attendance_score);

        return [
            'performance_score' => $performance,
            'behavior_score' => $behavior,
            'productivity_score' => $productivity,
            'interest_return_score' => $interestReturn,
            'attendance_score' => $attendance,
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

    /** App product total: equal average of five 0–100 metrics. */
    public function totalFromFive(
        float $performance,
        float $behavior,
        float $productivity,
        float $interestReturn,
        float $attendance,
    ): float {
        return round(($performance + $behavior + $productivity + $interestReturn + $attendance) / 5, 2);
    }

    /**
     * Excel J10 legacy: average of four metrics on 0–1 scale.
     * Inputs are 0–100 app scores; result matches sheet fixture **0.97375**.
     */
    public function legacyFourAverageAsFraction(
        float $performance,
        float $behavior,
        float $productivity,
        float $attendance,
    ): float {
        $avgPercent = ($performance + $behavior + $productivity + $attendance) / 4;

        return round($avgPercent / 100, 5);
    }

    /**
     * Upsert monthly row: recompute averages from daily scores + optional narrative.
     */
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

        $scores = $this->monthlyFromDaily($employee, $month, $year);

        return DB::transaction(function () use ($actor, $employee, $month, $year, $evaluationText, $scores) {
            $evaluation = Evaluation::query()->updateOrCreate(
                [
                    'user_id' => $employee->id,
                    'month' => $month,
                    'year' => $year,
                ],
                [
                    'team_leader_id' => $actor->id,
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

        return (int) $employee->team_leader_id === (int) $actor->id;
    }

    /** Team members (Manager: employees + TLs) for the monthly evaluation UI. */
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
                ->where('team_leader_id', $actor->id)
                ->orderBy('name_en')
                ->get();
        }

        return collect();
    }

    /**
     * Preview averages + saved text for one employee/month (UI).
     *
     * @return array{
     *     scores: array<string, float>,
     *     evaluation: ?Evaluation,
     *     evaluation_text: ?string
     * }
     */
    public function snapshot(User $employee, int $month, int $year): array
    {
        $scores = $this->monthlyFromDaily($employee, $month, $year);
        $evaluation = Evaluation::query()
            ->where('user_id', $employee->id)
            ->where('month', $month)
            ->where('year', $year)
            ->first();

        return [
            'scores' => $scores,
            'evaluation' => $evaluation,
            'evaluation_text' => $evaluation?->evaluation_text,
        ];
    }

    /** Saved monthly evaluations for the employee (newest first). */
    public function listForUser(User $user, ?int $year = null): Collection
    {
        $query = Evaluation::query()
            ->where('user_id', $user->id)
            ->orderByDesc('year')
            ->orderByDesc('month');

        if ($year !== null) {
            $query->where('year', $year);
        }

        return $query->get();
    }

    public function findForUserMonth(User $user, int $month, int $year): ?Evaluation
    {
        $this->assertMonthYear($month, $year);

        return Evaluation::query()
            ->where('user_id', $user->id)
            ->where('month', $month)
            ->where('year', $year)
            ->first();
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
