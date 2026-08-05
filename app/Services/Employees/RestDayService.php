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
    public function isRestDate(User $user, string|CarbonInterface $date): bool
    {
        $day = Carbon::parse($date)->toDateString();

        return RestDay::query()
            ->where('user_id', $user->id)
            ->whereDate('date', $day)
            ->exists();
    }

    public function add(User $actor, User $employee, string $date): RestDay
    {
        if (! $this->actorMayManage($actor, $employee)) {
            abort(403);
        }

        if ($employee->restDays()->whereDate('date', $date)->exists()) {
            throw ValidationException::withMessages([
                'date' => __('employees.rest_days.errors.duplicate'),
            ]);
        }

        return $employee->restDays()->create([
            'date' => $date,
            'created_by' => $actor->id,
        ]);
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

        if ($actor->isManager()) {
            return $employee->isEmployee() || $employee->isTeamLeader();
        }

        if ($actor->isTeamLeader()) {
            return $employee->isEmployee()
                && (int) $employee->team_leader_id === (int) $actor->id;
        }

        return false;
    }

    /** @return Collection<int, User> */
    public function manageableEmployees(User $actor): Collection
    {
        if ($actor->isManager()) {
            return User::query()
                ->whereIn('role', [User::ROLE_EMPLOYEE, User::ROLE_TEAM_LEADER])
                ->where('status', User::STATUS_ACTIVE)
                ->with(['restDays' => fn ($q) => $q->orderBy('date'), 'restDays.pendingReservation'])
                ->orderBy('name_en')
                ->get();
        }

        if ($actor->isTeamLeader()) {
            return User::query()
                ->where('role', User::ROLE_EMPLOYEE)
                ->where('team_leader_id', $actor->id)
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
