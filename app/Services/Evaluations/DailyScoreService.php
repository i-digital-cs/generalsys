<?php

namespace App\Services\Evaluations;

use App\Exceptions\DailyScoreException;
use App\Models\Attendance;
use App\Models\User;
use App\Services\Attendance\DailyReportService;
use Illuminate\Support\Facades\DB;

/**
 * Daily TL scores on attendance (T-3.1). Attendance % is system-computed on clock-out.
 */
class DailyScoreService
{
    public function __construct(private readonly DailyReportService $reports) {}

    /**
     * @param  array{performance_score: float|int|string, behavior_score: float|int|string, productivity_score: float|int|string, interest_return_score: float|int|string}  $scores
     */
    public function save(User $actor, Attendance $attendance, array $scores): Attendance
    {
        if (! $this->reports->actorMayRespond($actor, $attendance)) {
            throw DailyScoreException::notTeamMember();
        }

        $attendance->loadMissing('user');

        return DB::transaction(function () use ($attendance, $scores) {
            $attendance->update([
                'performance_score' => $this->normalize($scores['performance_score']),
                'behavior_score' => $this->normalize($scores['behavior_score']),
                'productivity_score' => $this->normalize($scores['productivity_score']),
                'interest_return_score' => $this->normalize($scores['interest_return_score']),
            ]);

            return $attendance->refresh();
        });
    }

    public function actorMayScore(User $actor, Attendance $attendance): bool
    {
        return $this->reports->actorMayRespond($actor, $attendance);
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
