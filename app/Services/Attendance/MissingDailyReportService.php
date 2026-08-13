<?php

namespace App\Services\Attendance;

use App\Models\Attendance;
use App\Models\OfficialHoliday;
use App\Models\User;
use App\Services\Employees\RestDayService;
use App\Services\Notifications\AppNotificationService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Missing daily reports: warn TL + Managers when a work day has no report.
 *
 * "Missing" = no attendance row at all (no-show) OR every row for the day has a
 * blank daily_report. Rest days, APPROVED/FORCED vacations, and official holidays
 * are never warned about.
 *
 * Contract start dates are intentionally NOT excluded (skipped: users without
 * contracts are the common case and the rule was not trivial to define).
 *
 * No dedupe in notifyForDate: the scheduler runs each kind once per day, so a
 * recipient gets at most one notification per person/date/kind.
 */
class MissingDailyReportService
{
    public const KIND_TODAY = 'report_missing_today';

    public const KIND_OVERDUE = 'report_missing_overdue';

    public function __construct(
        private readonly RestDayService $restDays,
        private readonly DailyReportService $reports,
        private readonly AppNotificationService $notifications,
    ) {}

    /**
     * All ACTIVE employees + team leaders missing a report for a work day.
     *
     * @return Collection<int, User>
     */
    public function missingPeopleForDate(CarbonInterface $date): Collection
    {
        $day = Carbon::parse($date)->toDateString();

        if (OfficialHoliday::query()->whereDate('date', $day)->exists()) {
            return collect();
        }

        $people = User::query()
            ->whereIn('role', [User::ROLE_EMPLOYEE, User::ROLE_TEAM_LEADER])
            ->where('status', User::STATUS_ACTIVE)
            ->orderBy('name_en')
            ->get();

        if ($people->isEmpty()) {
            return collect();
        }

        $reportedIds = Attendance::query()
            ->whereIn('user_id', $people->pluck('id'))
            ->whereDate('date', $day)
            ->whereNotNull('daily_report')
            ->where('daily_report', '!=', '')
            ->pluck('user_id')
            ->flip();

        return $people
            ->reject(fn (User $user) => $reportedIds->has($user->id))
            ->reject(fn (User $user) => $this->isOff($user, $day))
            ->values();
    }

    /**
     * Actor-scoped missing people: TL → own team employees; Manager → all
     * (employees + team leaders). Other roles see nothing.
     *
     * @return Collection<int, User>
     */
    public function missingForActor(User $actor, CarbonInterface $date): Collection
    {
        if ($actor->isManager()) {
            return $this->missingPeopleForDate($date);
        }

        if ($actor->isTeamLeader()) {
            return $this->missingPeopleForDate($date)
                ->filter(fn (User $user) => $user->isEmployee()
                    && (int) $user->team_leader_id === (int) $actor->id)
                ->values();
        }

        return collect();
    }

    /**
     * Notify each missing person's team leader (employees only) + all Managers.
     *
     * @return int recipients notified
     */
    public function notifyForDate(CarbonInterface $date, string $kind): int
    {
        $sent = 0;

        foreach ($this->missingPeopleForDate($date) as $person) {
            $attendance = $this->reports->mainAttendanceForDate($person, Carbon::parse($date)->toDateString());
            $sent += $this->notifications->reportMissing($attendance, $person, $date, $kind);
        }

        return $sent;
    }

    private function isOff(User $user, string $day): bool
    {
        if ($this->restDays->isRestDate($user, $day)) {
            return true;
        }

        return in_array($day, $this->restDays->approvedVacationDates($user, $day, $day), true);
    }
}
