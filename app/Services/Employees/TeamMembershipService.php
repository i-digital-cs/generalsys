<?php

namespace App\Services\Employees;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Who leads whom (T-8.1). Max two TEAM_LEADER rows per employee.
 */
class TeamMembershipService
{
    public function leads(User $actor, User $employee): bool
    {
        if (! $actor->isTeamLeader()) {
            return false;
        }

        return in_array((int) $actor->id, $this->leaderIds($employee), true);
    }

    /**
     * @return list<int>
     */
    public function leaderIds(User $employee): array
    {
        $ids = $employee->relationLoaded('teamLeaders')
            ? $employee->teamLeaders->pluck('id')
            : $employee->teamLeaders()->pluck('users.id');

        return $ids->map(fn ($id): int => (int) $id)->values()->all();
    }

    /**
     * @return list<int>
     */
    public function visibleEmployeeIds(User $actor): array
    {
        return $this->visibleEmployees($actor)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /** @return Collection<int, User> */
    public function visibleEmployees(User $actor): Collection
    {
        if ($actor->canManageOperations()) {
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
     * @param  list<int|string|null>  $leaderIds
     */
    public function syncLeaders(User $employee, array $leaderIds, ?User $assignedBy = null): void
    {
        $ids = [];
        foreach ($leaderIds as $raw) {
            if ($raw === null || $raw === '') {
                continue;
            }
            $id = (int) $raw;
            if ($id <= 0 || in_array($id, $ids, true)) {
                continue;
            }
            $ids[] = $id;
        }

        if (count($ids) > 2) {
            throw ValidationException::withMessages([
                'team_leader_id' => __('employees.team_leaders_max'),
            ]);
        }

        if ($ids !== []) {
            $valid = User::query()
                ->whereIn('id', $ids)
                ->where('role', User::ROLE_TEAM_LEADER)
                ->count();

            if ($valid !== count($ids)) {
                throw ValidationException::withMessages([
                    'team_leader_id' => __('employees.team_leaders_must_be_leaders'),
                ]);
            }
        }

        $payload = [];
        foreach ($ids as $id) {
            $payload[$id] = ['assigned_by' => $assignedBy?->id];
        }

        $employee->teamLeaders()->sync($payload);
        $employee->unsetRelation('teamLeaders');
    }
}
