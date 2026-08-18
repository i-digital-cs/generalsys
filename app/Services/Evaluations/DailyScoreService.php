<?php

namespace App\Services\Evaluations;

use App\Exceptions\DailyScoreException;
use App\Models\Attendance;
use App\Models\AttendanceDailyScore;
use App\Models\User;
use App\Services\Attendance\DailyReportService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Per-TL daily scores (T-3.1 / T-8.5). Attendance % stays on attendance.
 */
class DailyScoreService
{
    public function __construct(private readonly DailyReportService $reports) {}

    /**
     * @param  array{performance_score: float|int|string, behavior_score: float|int|string, productivity_score: float|int|string, interest_return_score: float|int|string}  $scores
     */
    public function save(User $actor, Attendance $attendance, array $scores): AttendanceDailyScore
    {
        if (! $this->actorMayScore($actor, $attendance)) {
            throw DailyScoreException::notTeamMember();
        }

        return DB::transaction(function () use ($actor, $attendance, $scores) {
            $row = AttendanceDailyScore::query()->updateOrCreate(
                [
                    'attendance_id' => $attendance->id,
                    'team_leader_id' => $actor->id,
                ],
                [
                    'scored_by' => $actor->id,
                    'performance_score' => $this->normalize($scores['performance_score']),
                    'behavior_score' => $this->normalize($scores['behavior_score']),
                    'productivity_score' => $this->normalize($scores['productivity_score']),
                    'interest_return_score' => $this->normalize($scores['interest_return_score']),
                ],
            );

            return $row->refresh();
        });
    }

    public function actorMayScore(User $actor, Attendance $attendance): bool
    {
        return $this->reports->actorMayRespond($actor, $attendance);
    }

    public function forActor(Attendance $attendance, User $actor): ?AttendanceDailyScore
    {
        $attendance->loadMissing('dailyScores.teamLeader');

        return $attendance->dailyScores
            ->first(fn (AttendanceDailyScore $row) => (int) $row->team_leader_id === (int) $actor->id);
    }

    /** @return Collection<int, AttendanceDailyScore> */
    public function othersForActor(Attendance $attendance, User $actor): Collection
    {
        $attendance->loadMissing('dailyScores.teamLeader');

        return $attendance->dailyScores
            ->filter(fn (AttendanceDailyScore $row) => (int) $row->team_leader_id !== (int) $actor->id)
            ->values();
    }

    /**
     * @return array{
     *     performance_score: float,
     *     behavior_score: float,
     *     productivity_score: float,
     *     interest_return_score: float
     * }
     */
    public function monthAverages(User $user, int $month, int $year, ?int $evaluatorId = null): array
    {
        $from = Carbon::create($year, $month, 1)->startOfDay()->toDateString();
        $to = Carbon::create($year, $month, 1)->endOfMonth()->toDateString();

        $query = AttendanceDailyScore::query()
            ->join('attendance', 'attendance.id', '=', 'attendance_daily_scores.attendance_id')
            ->where('attendance.user_id', $user->id)
            ->whereDate('attendance.date', '>=', $from)
            ->whereDate('attendance.date', '<=', $to);

        if ($evaluatorId !== null) {
            $query->where('attendance_daily_scores.team_leader_id', $evaluatorId);
        }

        $agg = $query
            ->selectRaw('AVG(attendance_daily_scores.performance_score) as performance_score')
            ->selectRaw('AVG(attendance_daily_scores.behavior_score) as behavior_score')
            ->selectRaw('AVG(attendance_daily_scores.productivity_score) as productivity_score')
            ->selectRaw('AVG(attendance_daily_scores.interest_return_score) as interest_return_score')
            ->first();

        return [
            'performance_score' => $this->asScore($agg?->performance_score),
            'behavior_score' => $this->asScore($agg?->behavior_score),
            'productivity_score' => $this->asScore($agg?->productivity_score),
            'interest_return_score' => $this->asScore($agg?->interest_return_score),
        ];
    }

    private function asScore(mixed $value): float
    {
        if ($value === null) {
            return 0.0;
        }

        return round((float) $value, 2);
    }

    private function normalize(float|int|string $value): float
    {
        $score = round((float) $value, 2);

        if ($score < 0 || $score > 100) {
            throw DailyScoreException::outOfRange();
        }

        return $score;
    }
}
