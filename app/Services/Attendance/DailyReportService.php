<?php

namespace App\Services\Attendance;

use App\Exceptions\DailyReportException;
use App\Models\Attendance;
use App\Models\User;
use App\Services\Notifications\AppNotificationService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Employee daily report (T-2.5) + TL reply (T-2.6).
 *
 * Rules (locked 2026-08-03):
 * - One report per calendar day, stored on the day’s first attendance row (earliest clock_in).
 * - Submit only after that row is clocked out.
 * - Locked once team_leader_response is set (T-2.6 writes the reply).
 */
class DailyReportService
{
    public function __construct(private readonly AppNotificationService $notifications) {}

    public function mainAttendanceForDate(User $user, string $date): ?Attendance
    {
        return Attendance::query()
            ->where('user_id', $user->id)
            ->whereDate('date', $date)
            ->orderBy('clock_in')
            ->orderBy('id')
            ->first();
    }

    public function isLocked(?Attendance $attendance): bool
    {
        if ($attendance === null) {
            return false;
        }

        return filled($attendance->team_leader_response);
    }

    public function canSubmit(?Attendance $attendance): bool
    {
        if ($attendance === null) {
            return false;
        }

        if ($attendance->clock_out === null) {
            return false;
        }

        return ! $this->isLocked($attendance);
    }

    public function submit(User $user, string $date, string $reportText): Attendance
    {
        $date = Carbon::parse($date)->toDateString();
        $attendance = $this->mainAttendanceForDate($user, $date);

        if ($attendance === null) {
            throw DailyReportException::noAttendance();
        }

        if ($attendance->clock_out === null) {
            throw DailyReportException::clockOutRequired();
        }

        if ($this->isLocked($attendance)) {
            throw DailyReportException::lockedByTlResponse();
        }

        return DB::transaction(function () use ($attendance, $reportText) {
            $attendance->update(['daily_report' => $reportText]);
            $attendance = $attendance->refresh();
            $this->notifications->reportSubmitted($attendance);

            return $attendance;
        });
    }

    /**
     * Recent days that have a closed main attendance (for history list).
     *
     * @return Collection<int, Attendance>
     */
    public function recentMainAttendances(User $user, int $limit = 14): Collection
    {
        $rows = Attendance::query()
            ->where('user_id', $user->id)
            ->whereNotNull('clock_out')
            ->orderByDesc('date')
            ->orderBy('clock_in')
            ->limit(100)
            ->get();

        return $rows
            ->groupBy(fn (Attendance $row) => $row->date->toDateString())
            ->map(fn (Collection $dayRows) => $dayRows->sortBy('clock_in')->first())
            ->values()
            ->take($limit);
    }

    public function snapshot(User $user, ?CarbonInterface $forDate = null): array
    {
        $date = Carbon::parse($forDate ?? now())->toDateString();
        $main = $this->mainAttendanceForDate($user, $date);

        return [
            'date' => $date,
            'main' => $main,
            'canSubmit' => $this->canSubmit($main),
            'isLocked' => $this->isLocked($main),
            'recent' => $this->recentMainAttendances($user),
        ];
    }

    /**
     * Team queue of main-day rows that have an employee report.
     *
     * @param  'pending'|'replied'|'all'  $filter
     */
    public function teamReportsForActor(
        User $actor,
        string $filter = 'all',
        ?int $employeeId = null,
        int $perPage = 25,
    ): LengthAwarePaginator {
        $filter = in_array($filter, ['pending', 'replied', 'all'], true) ? $filter : 'all';
        $perPage = min(max($perPage, 1), 50);
        $teamIds = $this->visibleEmployeeIds($actor);

        if ($employeeId !== null && ! $teamIds->contains($employeeId)) {
            $employeeId = null;
        }

        $query = Attendance::query()
            ->with('user')
            ->whereIn('user_id', $teamIds)
            ->whereNotNull('daily_report')
            ->where('daily_report', '!=', '')
            ->whereNotNull('clock_out')
            ->orderByDesc('date')
            ->orderBy('clock_in')
            ->limit(500);

        if ($employeeId !== null) {
            $query->where('user_id', $employeeId);
        }

        $rows = $query->get()
            ->groupBy(fn (Attendance $row) => $row->user_id.'|'.$row->date->toDateString())
            ->map(fn (Collection $dayRows) => $dayRows->sortBy('clock_in')->first())
            ->values();

        $rows = match ($filter) {
            'replied' => $rows->filter(fn (Attendance $row) => filled($row->team_leader_response))->values(),
            'pending' => $rows->filter(fn (Attendance $row) => blank($row->team_leader_response))->values(),
            default => $rows,
        };

        return $this->paginateCollection($rows, $perPage);
    }

    /** Employees (and for Manager: TLs too) available for the reports filter. */
    public function filterableUsers(User $actor): Collection
    {
        $ids = $this->visibleEmployeeIds($actor);

        return User::query()
            ->whereIn('id', $ids)
            ->orderBy('name_en')
            ->get();
    }

    /** @param  Collection<int, Attendance>  $items */
    private function paginateCollection(Collection $items, int $perPage): LengthAwarePaginator
    {
        $page = Paginator::resolveCurrentPage();

        return new Paginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            [
                'path' => Paginator::resolveCurrentPath(),
                'query' => request()->query(),
            ],
        );
    }

    public function respond(User $actor, Attendance $attendance, string $response): Attendance
    {
        if (! $this->actorMayRespond($actor, $attendance)) {
            throw DailyReportException::notTeamMember();
        }

        if (blank($attendance->daily_report)) {
            throw DailyReportException::reportMissing();
        }

        if ($attendance->clock_out === null) {
            throw DailyReportException::clockOutRequired();
        }

        // Ensure we reply on the day's main row.
        $main = $this->mainAttendanceForDate($attendance->user, $attendance->date->toDateString());

        if ($main === null || $main->id !== $attendance->id) {
            throw DailyReportException::reportMissing();
        }

        return DB::transaction(function () use ($main, $response, $actor) {
            $main->update(['team_leader_response' => $response]);
            $main = $main->refresh();
            $this->notifications->reportReplied($main, $actor);

            return $main;
        });
    }

    public function actorMayRespond(User $actor, Attendance $attendance): bool
    {
        $attendance->loadMissing('user');

        if ($actor->isManager()) {
            return true;
        }

        if (! $actor->isTeamLeader()) {
            return false;
        }

        return (int) $attendance->user?->team_leader_id === (int) $actor->id;
    }

    /** @return Collection<int, int> */
    private function visibleEmployeeIds(User $actor): Collection
    {
        // Manager reviews employees + team leaders (+ other managers' reports).
        if ($actor->isManager()) {
            return User::query()
                ->whereIn('role', [
                    User::ROLE_EMPLOYEE,
                    User::ROLE_TEAM_LEADER,
                    User::ROLE_MANAGER,
                ])
                ->where('status', User::STATUS_ACTIVE)
                ->pluck('id');
        }

        $query = User::query()
            ->where('role', User::ROLE_EMPLOYEE)
            ->where('status', User::STATUS_ACTIVE);

        if ($actor->isTeamLeader()) {
            $query->where('team_leader_id', $actor->id);
        } else {
            return collect();
        }

        return $query->pluck('id');
    }
}
