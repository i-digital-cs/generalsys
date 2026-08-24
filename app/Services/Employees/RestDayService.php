<?php

namespace App\Services\Employees;

use App\Models\RestDay;
use App\Models\User;
use App\Models\Vacation;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Date-based rest days (schema §20). Admin + TL for team.
 * Vacation/emergency swap consumes one rest date on approve.
 */
class RestDayService
{
    public function __construct(private readonly TeamMembershipService $membership) {}

    public function isRestDate(User $user, string|CarbonInterface $date): bool
    {
        $day = Carbon::parse($date)->toDateString();

        return RestDay::query()
            ->where('user_id', $user->id)
            ->whereDate('date', $day)
            ->exists();
    }

    /**
     * Rest-day dates for a calendar month (Y-m-d strings).
     *
     * @return list<string>
     */
    public function datesInMonth(User $user, int $month, int $year): array
    {
        $from = Carbon::create($year, $month, 1)->startOfDay();
        $to = $from->copy()->endOfMonth();

        return RestDay::query()
            ->where('user_id', $user->id)
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->toDateString())
            ->orderBy('date')
            ->pluck('date')
            ->map(fn ($d) => Carbon::parse($d)->toDateString())
            ->unique()
            ->values()
            ->all();
    }

    public function add(User $actor, User $employee, string $date): RestDay
    {
        if (! $this->actorMayManage($actor, $employee)) {
            abort(403);
        }

        if ($employee->restDays()->whereDate('date', $date)->exists()) {
            throw ValidationException::withMessages([
                'date' => __('employees.rest_days.errors.duplicate'),
                'dates' => __('employees.rest_days.errors.duplicate'),
            ]);
        }

        return $employee->restDays()->create([
            'date' => $date,
            'created_by' => $actor->id,
        ]);
    }

    /**
     * Add many rest dates (separate days, e.g. 7th + 14th + 24th). Skips dates already marked.
     *
     * @param  list<string>  $dates  Y-m-d strings
     * @return array{added: int, skipped: int}
     */
    public function addMany(User $actor, User $employee, array $dates): array
    {
        if (! $this->actorMayManage($actor, $employee)) {
            abort(403);
        }

        $unique = collect($dates)
            ->filter(fn ($d) => is_string($d) && $d !== '')
            ->map(fn (string $d) => Carbon::parse($d)->toDateString())
            ->unique()
            ->values();

        if ($unique->isEmpty()) {
            throw ValidationException::withMessages([
                'dates' => __('employees.rest_days.errors.no_dates'),
            ]);
        }

        $existing = $employee->restDays()
            ->whereDate('date', '>=', $unique->min())
            ->whereDate('date', '<=', $unique->max())
            ->get()
            ->map(fn (RestDay $row) => $row->date->toDateString())
            ->all();

        $existingLookup = array_fill_keys($existing, true);
        $added = 0;
        $skipped = 0;

        foreach ($unique as $day) {
            if (isset($existingLookup[$day])) {
                $skipped++;

                continue;
            }

            $employee->restDays()->create([
                'date' => $day,
                'created_by' => $actor->id,
            ]);
            $existingLookup[$day] = true;
            $added++;
        }

        if ($added === 0) {
            throw ValidationException::withMessages([
                'dates' => __('employees.rest_days.errors.all_duplicates'),
            ]);
        }

        return ['added' => $added, 'skipped' => $skipped];
    }

    public function remove(User $actor, RestDay $restDay): void
    {
        $restDay->loadMissing('user');

        if ($restDay->user === null || ! $this->actorMayManage($actor, $restDay->user)) {
            abort(403);
        }

        if ($this->isReservedByPendingVacation($restDay)) {
            throw ValidationException::withMessages([
                'date' => __('employees.rest_days.errors.reserved'),
            ]);
        }

        $restDay->delete();
    }

    public function actorMayManage(User $actor, User $employee): bool
    {
        if (! $employee->isActive()) {
            return false;
        }

        if ($actor->canManageOperations()) {
            return $employee->isFieldStaff() || $employee->isTeamLeader();
        }

        if ($actor->isTeamLeader()) {
            return $employee->isFieldStaff()
                && $this->membership->leads($actor, $employee);
        }

        return false;
    }

    /** @return Collection<int, User> */
    public function manageableEmployees(User $actor): Collection
    {
        if ($actor->canManageOperations()) {
            return User::query()
                ->whereIn('role', User::operationsVisibleRoles())
                ->where('status', User::STATUS_ACTIVE)
                ->with(['restDays' => fn ($q) => $q->orderBy('date'), 'restDays.pendingReservation'])
                ->orderBy('name_en')
                ->get();
        }

        if ($actor->isTeamLeader()) {
            return User::query()
                ->whereIn('role', User::fieldStaffRoles())
                ->ledBy($actor->id)
                ->where('status', User::STATUS_ACTIVE)
                ->with(['restDays' => fn ($q) => $q->orderBy('date'), 'restDays.pendingReservation'])
                ->orderBy('name_en')
                ->get();
        }

        return collect();
    }

    /**
     * Rest days still available to swap (not reserved by PENDING vacation).
     *
     * @return Collection<int, RestDay>
     */
    public function availableForSwap(User $user, ?CarbonInterface $from = null): Collection
    {
        $reservedIds = Vacation::query()
            ->where('user_id', $user->id)
            ->where('status', Vacation::STATUS_PENDING)
            ->whereNotNull('replace_rest_day_id')
            ->pluck('replace_rest_day_id');

        $query = RestDay::query()
            ->where('user_id', $user->id)
            ->whereNotIn('id', $reservedIds)
            ->orderBy('date');

        if ($from !== null) {
            $query->whereDate('date', '>=', Carbon::parse($from)->toDateString());
        }

        return $query->get();
    }

    public function findOwned(User $user, int $restDayId): ?RestDay
    {
        return RestDay::query()
            ->where('user_id', $user->id)
            ->where('id', $restDayId)
            ->first();
    }

    public function isReservedByPendingVacation(RestDay $restDay): bool
    {
        return Vacation::query()
            ->where('replace_rest_day_id', $restDay->id)
            ->where('status', Vacation::STATUS_PENDING)
            ->exists();
    }

    /** Dates covered by APPROVED or FORCED vacations in [from, to]. @return list<string> */
    public function approvedVacationDates(User $user, string $from, string $to): array
    {
        $rows = Vacation::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [Vacation::STATUS_APPROVED, Vacation::STATUS_FORCED])
            ->whereDate('start_date', '<=', $to)
            ->whereDate('end_date', '>=', $from)
            ->get(['start_date', 'end_date']);

        $dates = [];
        foreach ($rows as $row) {
            $cursor = Carbon::parse($row->start_date)->startOfDay();
            $end = Carbon::parse($row->end_date)->startOfDay();
            while ($cursor->lte($end)) {
                $d = $cursor->toDateString();
                if ($d >= $from && $d <= $to) {
                    $dates[] = $d;
                }
                $cursor->addDay();
            }
        }

        return array_values(array_unique($dates));
    }
}
