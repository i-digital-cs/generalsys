<?php

namespace App\Services\Attendance;

use App\Models\Attendance;
use App\Models\RestDay;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Models\Vacation;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;

/**
 * Paginated attendance history for ops follow-up (T-7.6).
 */
class AttendanceHistoryService
{
    public const MAX_RANGE_DAYS = 92;

    public const KINDS = ['all', 'present', 'absent', 'shortfall', 'surplus', 'rest', 'vacation'];

    public const EVIDENCE = ['all', 'photos', 'location', 'outside'];

    public function __construct(
        private readonly HoursComparisonService $hours,
        private readonly AttendanceCalculationEngine $calculations,
        private readonly AttendanceGracePolicy $grace,
        private readonly AttendanceReviewPresentation $reviewPresentation,
    ) {}

    /**
     * @param  array{
     *     employee_id?: ?int,
     *     from: string,
     *     to: string,
     *     kind: string,
     *     evidence: string,
     *     page?: int,
     *     path?: string,
     *     query?: array<string, mixed>
     * }  $filters
     */
    public function paginate(User $actor, array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $users = $this->scopedUsers($actor, isset($filters['employee_id']) ? (int) $filters['employee_id'] : null);
        $rows = $this->rowsForUsers($users, $filters['from'], $filters['to'])
            ->filter(fn (array $row): bool => $this->matches($row, $filters))
            ->values();

        $perPage = min(50, max(1, $perPage));
        $total = $rows->count();
        $page = max(1, (int) ($filters['page'] ?? Paginator::resolveCurrentPage()));
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);

        $paginator = new Paginator(
            $rows->forPage($page, $perPage)->values(),
            $total,
            $perPage,
            $page,
            ['path' => $filters['path'] ?? Paginator::resolveCurrentPath()],
        );

        return $paginator->appends($this->queryString($filters));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function queryString(array $filters): array
    {
        $query = [];
        foreach (['employee_id', 'from', 'to', 'kind', 'evidence'] as $key) {
            if (! array_key_exists($key, $filters) || $filters[$key] === null || $filters[$key] === '') {
                continue;
            }
            $query[$key] = $filters[$key];
        }

        return $query;
    }

    /** @return Collection<int, User> */
    public function filterableUsers(User $actor): Collection
    {
        return $this->hours->visibleEmployees($actor)->load('teamLeaders');
    }

    /**
     * @return Collection<int, User>
     */
    private function scopedUsers(User $actor, ?int $employeeId): Collection
    {
        $users = $this->hours->visibleEmployees($actor)->load('teamLeaders');

        if ($employeeId === null || $employeeId === 0) {
            return $users;
        }

        $match = $users->firstWhere('id', $employeeId);
        abort_unless($match instanceof User, 403);

        return collect([$match]);
    }

    /**
     * @param  Collection<int, User>  $users
     * @return Collection<int, array<string, mixed>>
     */
    private function rowsForUsers(Collection $users, string $from, string $to): Collection
    {
        if ($users->isEmpty()) {
            return collect();
        }

        $ids = $users->pluck('id')->all();
        $assignments = $this->assignmentsByUserDate($ids, $from, $to);
        $attendances = $this->attendancesByUserDate($ids, $from, $to);
        $rest = $this->restLookup($ids, $from, $to);
        $vacation = $this->vacationLookup($ids, $from, $to);

        $rows = collect();
        $cursor = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->startOfDay();

        while ($cursor->lte($end)) {
            $date = $cursor->toDateString();
            foreach ($users as $user) {
                $rows->push($this->rowForDay(
                    $user,
                    $date,
                    $assignments[$user->id][$date] ?? collect(),
                    $attendances[$user->id][$date] ?? collect(),
                    isset($rest[$user->id][$date]),
                    isset($vacation[$user->id][$date]),
                ));
            }
            $cursor->addDay();
        }

        return $rows->sortByDesc(fn (array $row): string => $row['date'].'-'.$row['user']->name_en)->values();
    }

    /**
     * @param  Collection<int, ShiftAssignment>  $assignments
     * @param  Collection<int, Attendance>  $attendances
     * @return array<string, mixed>
     */
    private function rowForDay(
        User $user,
        string $date,
        Collection $assignments,
        Collection $attendances,
        bool $isRest,
        bool $isVacation,
    ): array {
        $planned = $this->hours->plannedForDay($user, $assignments, $isRest || $isVacation);
        $actual = $this->hours->actualForDay($attendances);
        $photos = $attendances->flatMap(fn (Attendance $row) => $row->photos)->values();
        $outside = $attendances->contains(fn (Attendance $row): bool => $row->clock_in_outside_geofence
            || $row->clock_out_outside_geofence);
        $hasLocation = $attendances->contains(fn (Attendance $row): bool => $row->clock_in_lat !== null
            || $row->clock_out_lat !== null);

        $hasStarted = $attendances->contains(
            fn (Attendance $row): bool => $row->clock_in !== null
        );
        $rawHours = round((float) $attendances->sum(
            fn (Attendance $row) => (float) ($row->raw_actual_hours ?? $row->actual_hours)
        ), 2);
        $graceActive = $this->grace->isActiveForAttendanceDate($date);
        $durationBalance = $this->calculations->dayDurationBalance(
            $attendances,
            (float) $planned['hours'],
            $date,
        );
        $kind = $this->kind(
            $isVacation,
            $isRest,
            (float) $planned['hours'],
            (float) $actual['hours'],
            $hasStarted,
            $durationBalance,
            $graceActive,
        );

        $sessions = $attendances
            ->filter(fn (Attendance $row): bool => $row->clock_in !== null)
            ->map(function (Attendance $row): array {
                $calc = $this->calculations->forAttendance($row);

                return [
                    'attendance' => $row,
                    'calculation' => $calc,
                ];
            })
            ->values();
        $sessionCalcs = $sessions->pluck('calculation');
        $hasUnverifiedDuration = $sessionCalcs->contains(
            fn (AttendanceCalculationResult $calc): bool => ! $calc->isOpen() && ! $calc->durationVerified()
        );
        $hasShiftReview = $sessionCalcs->contains(
            fn (AttendanceCalculationResult $calc): bool => $calc->shiftReviewRequired()
        );
        $verifiedHours = round((float) $sessionCalcs->sum(
            fn (AttendanceCalculationResult $calc): float => $calc->verifiedWorkedHours() ?? 0.0
        ), 2);
        $recordedHours = round((float) $sessionCalcs->sum(
            fn (AttendanceCalculationResult $calc): float => $calc->recordedWorkedHours()
        ), 2);
        $punctuality = $this->punctualityForSessions($attendances, $sessionCalcs);
        $overtimeEligible = (int) $sessionCalcs->sum(
            fn (AttendanceCalculationResult $calc): int => $calc->overtimeEligibleMinutes()
        );
        $overtimeApproved = (int) $sessionCalcs->sum(
            fn (AttendanceCalculationResult $calc): int => $calc->approvedOvertimeMinutes()
        );
        $creditedReviewRequired = $sessionCalcs->contains(
            fn (AttendanceCalculationResult $calc): bool => $calc->creditedReviewRequired()
        );
        $creditedProvenance = $sessionCalcs
            ->map(fn (AttendanceCalculationResult $calc): string => $calc->creditedProvenance())
            ->filter(fn (string $p): bool => $p !== 'NONE')
            ->unique()
            ->values()
            ->all();
        $reviewResults = $sessionCalcs->all();
        $reviewReasonCodes = $this->reviewPresentation->dayReasonCodes($reviewResults);
        $reviewTooltip = $this->reviewPresentation->dayTooltip($reviewResults);

        return [
            'user' => $user,
            'date' => $date,
            'kind' => $kind,
            'punctuality' => $punctuality,
            'planned_hours' => $planned['hours'],
            'actual_hours' => $actual['hours'],
            'absent_hours' => round(max(0, (float) $planned['hours'] - (float) $actual['hours']), 2),
            'is_rest_day' => $isRest,
            'is_vacation' => $isVacation,
            'photos' => $photos,
            'has_photos' => $photos->isNotEmpty(),
            'has_location' => $hasLocation,
            'outside' => $outside,
            'attendance' => $attendances->sortByDesc('id')->first(),
            'attendances' => $attendances,
            'sessions' => $sessions,
            'has_unverified_duration' => $hasUnverifiedDuration,
            'has_shift_review' => $hasShiftReview,
            'needs_review' => $reviewReasonCodes !== [],
            'review_reason_codes' => $reviewReasonCodes,
            'review_tooltip' => $reviewTooltip,
            'credited_review_required' => $creditedReviewRequired,
            'credited_provenance' => $creditedProvenance,
            'verified_hours' => $verifiedHours,
            'recorded_hours' => $recordedHours > 0 ? $recordedHours : $rawHours,
            'late_minutes' => $this->calculations->sumExactLateMinutes($attendances),
            'early_departure_minutes' => $this->calculations->sumExactEarlyMinutes($attendances),
            'raw_hours' => $rawHours,
            'credited_hours' => round((float) $actual['hours'], 2),
            'gap_minutes' => (int) $attendances->sum(fn (Attendance $row) => (int) $row->gaps->sum('minutes')),
            'recommended_overtime_minutes' => (int) $attendances->sum('recommended_overtime_minutes'),
            'overtime_eligible_minutes' => $overtimeEligible,
            'overtime_approved_minutes' => $overtimeApproved,
            'hours_balance' => $durationBalance,
        ];
    }

    /**
     * Punctuality is independent of day presence kind (attended ≠ on time).
     *
     * @param  Collection<int, Attendance>  $attendances
     * @param  Collection<int, AttendanceCalculationResult>  $sessionCalcs
     */
    private function punctualityForSessions(Collection $attendances, Collection $sessionCalcs): string
    {
        if ($attendances->isEmpty() || $sessionCalcs->isEmpty()) {
            return 'none';
        }

        $statuses = $attendances
            ->filter(fn (Attendance $row): bool => $row->clock_in !== null)
            ->pluck('status')
            ->filter()
            ->all();

        if (in_array(Attendance::STATUS_LATE, $statuses, true)) {
            return 'late';
        }
        if (in_array(Attendance::STATUS_EARLY_LEAVE, $statuses, true)) {
            return 'early_leave';
        }

        $lateTotal = (int) $sessionCalcs->sum(
            fn (AttendanceCalculationResult $calc): int => $calc->countedLateMinutes()
        );
        if ($lateTotal > 0) {
            return 'late';
        }

        $earlyTotal = (int) $sessionCalcs->sum(
            fn (AttendanceCalculationResult $calc): int => $calc->countedEarlyMinutes()
        );
        if ($earlyTotal > 0) {
            return 'early_leave';
        }

        return 'on_time';
    }

    /**
     * @param  array<string, int|string|bool>|null  $durationBalance
     */
    private function kind(
        bool $isVacation,
        bool $isRest,
        float $planned,
        float $actual,
        bool $hasStarted,
        ?array $durationBalance,
        bool $graceActive,
    ): string {
        if ($isVacation && $actual < 0.01 && ! $hasStarted) {
            return 'vacation';
        }
        if ($isRest && $actual < 0.01 && ! $hasStarted) {
            return 'rest';
        }
        // Absent = scheduled day with no punch at all (never started).
        if ($planned > 0.01 && ! $hasStarted && $actual < 0.01) {
            return 'absent';
        }

        if ($graceActive && $durationBalance !== null) {
            $countedDeficit = (int) ($durationBalance['counted_deficit_minutes'] ?? 0);
            $countedSurplus = (int) ($durationBalance['counted_surplus_minutes'] ?? 0);

            if ($planned > 0.01 && $hasStarted && $countedDeficit > 0) {
                return 'shortfall';
            }
            if ($hasStarted && $countedSurplus > 0) {
                return 'surplus';
            }
        } elseif ($planned > 0.01 && ($actual + 0.01) < $planned) {
            // Pre-effective-date: legacy shortfall = credited hours below planned.
            return 'shortfall';
        }

        if ($actual > 0.01 || $hasStarted) {
            return 'present';
        }
        if ($isVacation) {
            return 'vacation';
        }
        if ($isRest) {
            return 'rest';
        }

        return 'present';
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $filters
     */
    private function matches(array $row, array $filters): bool
    {
        $kind = $filters['kind'] ?? 'all';
        if ($kind !== 'all' && $row['kind'] !== $kind) {
            return false;
        }

        return match ($filters['evidence'] ?? 'all') {
            'photos' => $row['has_photos'] === true,
            'location' => $row['has_location'] === true,
            'outside' => $row['outside'] === true,
            default => true,
        };
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, array<string, Collection<int, ShiftAssignment>>>
     */
    private function assignmentsByUserDate(array $ids, string $from, string $to): array
    {
        $grouped = [];
        ShiftAssignment::query()
            ->whereIn('user_id', $ids)
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            ->whereIn('status', [
                ShiftAssignment::STATUS_SCHEDULED,
                ShiftAssignment::STATUS_COMPLETED,
            ])
            ->orderBy('expected_start_time')
            ->get()
            ->each(function (ShiftAssignment $row) use (&$grouped): void {
                $date = $row->date->toDateString();
                $grouped[$row->user_id][$date] ??= collect();
                $grouped[$row->user_id][$date]->push($row);
            });

        return $grouped;
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, array<string, Collection<int, Attendance>>>
     */
    private function attendancesByUserDate(array $ids, string $from, string $to): array
    {
        $grouped = [];
        Attendance::query()
            ->whereIn('user_id', $ids)
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            ->with(['photos', 'segments', 'gaps'])
            ->orderBy('clock_in')
            ->get()
            ->each(function (Attendance $row) use (&$grouped): void {
                $date = $row->date->toDateString();
                $grouped[$row->user_id][$date] ??= collect();
                $grouped[$row->user_id][$date]->push($row);
            });

        return $grouped;
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, array<string, true>>
     */
    private function restLookup(array $ids, string $from, string $to): array
    {
        $lookup = [];
        RestDay::query()
            ->whereIn('user_id', $ids)
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            ->get(['user_id', 'date'])
            ->each(function (RestDay $row) use (&$lookup): void {
                $lookup[$row->user_id][$row->date->toDateString()] = true;
            });

        return $lookup;
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, array<string, true>>
     */
    private function vacationLookup(array $ids, string $from, string $to): array
    {
        $lookup = [];
        Vacation::query()
            ->whereIn('user_id', $ids)
            ->whereIn('status', [Vacation::STATUS_APPROVED, Vacation::STATUS_FORCED])
            ->whereDate('start_date', '<=', $to)
            ->whereDate('end_date', '>=', $from)
            ->get(['user_id', 'start_date', 'end_date'])
            ->each(function (Vacation $row) use (&$lookup, $from, $to): void {
                $cursor = Carbon::parse($row->start_date)->startOfDay();
                $end = Carbon::parse($row->end_date)->startOfDay();
                while ($cursor->lte($end)) {
                    $d = $cursor->toDateString();
                    if ($d >= $from && $d <= $to) {
                        $lookup[$row->user_id][$d] = true;
                    }
                    $cursor->addDay();
                }
            });

        return $lookup;
    }
}
