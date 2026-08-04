<?php

namespace App\Services\Permissions;

use App\Exceptions\PermissionRequestException;
use App\Models\Permission;
use App\Models\User;
use App\Services\Notifications\AppNotificationService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Leave/late permission requests (T-2.7–T-2.9).
 * Submit: any role (self). Employees need a team_leader_id; TL/Manager may omit (Admin decides).
 * Force: self, TL (team), or Manager. Penalty amounts never exposed on TL UI.
 */
class PermissionRequestService
{
    public function __construct(
        private readonly PermissionPenaltyCalculator $penalties,
        private readonly AppNotificationService $notifications,
    ) {}

    /**
     * @param  array{type: string, request_date: string, from_time: string, to_time: string, reason?: ?string}  $data
     */
    public function submit(User $user, array $data): Permission
    {
        if ($user->isEmployee() && $user->team_leader_id === null) {
            throw PermissionRequestException::noTeamLeader();
        }

        $from = $this->normalizeTime($data['from_time']);
        $to = $this->normalizeTime($data['to_time']);

        if ($to <= $from) {
            throw PermissionRequestException::invalidTimeRange();
        }

        return DB::transaction(function () use ($user, $data, $from, $to) {
            $permission = Permission::query()->create([
                'user_id' => $user->id,
                'type' => $data['type'],
                'request_date' => Carbon::parse($data['request_date'])->toDateString(),
                'from_time' => $from,
                'to_time' => $to,
                'reason' => $data['reason'] ?? null,
                'status' => Permission::STATUS_PENDING,
                'team_leader_id' => $user->team_leader_id,
                'penalty_amount' => 0,
                'is_double_penalty' => false,
            ]);

            $this->notifications->permissionSubmitted($permission);

            return $permission;
        });
    }

    public function listForUser(User $user, int $perPage = 25): LengthAwarePaginator
    {
        $perPage = min(max($perPage, 1), 50);

        return Permission::query()
            ->where('user_id', $user->id)
            ->orderByDesc('request_date')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function pendingForActor(User $actor, int $perPage = 25): LengthAwarePaginator
    {
        return $this->listForActor($actor, 'pending', $perPage);
    }

    /**
     * @param  'pending'|'decided'|'all'  $filter
     */
    public function listForActor(User $actor, string $filter = 'pending', int $perPage = 25): LengthAwarePaginator
    {
        $perPage = min(max($perPage, 1), 50);
        $filter = in_array($filter, ['pending', 'decided', 'all'], true) ? $filter : 'pending';

        $query = Permission::query()->with('user');

        if ($actor->isTeamLeader()) {
            $query->where('team_leader_id', $actor->id);
        } elseif (! $actor->isManager()) {
            abort(403);
        }

        if ($filter === 'pending') {
            $query->where('status', Permission::STATUS_PENDING)
                ->orderBy('request_date')
                ->orderBy('id');
        } elseif ($filter === 'decided') {
            $query->whereIn('status', [
                Permission::STATUS_ACCEPTED,
                Permission::STATUS_REJECTED,
                Permission::STATUS_FORCED,
            ])
                ->orderByDesc('request_date')
                ->orderByDesc('id');
        } else {
            $query->orderByDesc('request_date')->orderByDesc('id');
        }

        return $query->paginate($perPage, ['*'], 'perm_page')->withQueryString();
    }

    public function approve(User $actor, Permission $permission): Permission
    {
        $this->assertActorMayDecide($actor, $permission);
        $this->assertPending($permission);

        return DB::transaction(function () use ($permission, $actor) {
            $permission->update([
                'status' => Permission::STATUS_ACCEPTED,
                'penalty_amount' => 0,
                'is_double_penalty' => false,
            ]);

            $permission = $permission->refresh();
            $this->notifications->permissionDecided($permission, $actor);

            return $permission;
        });
    }

    public function reject(User $actor, Permission $permission): Permission
    {
        $this->assertActorMayDecide($actor, $permission);
        $this->assertPending($permission);

        $amount = $this->penalties->amountForRejection($permission);

        return DB::transaction(function () use ($permission, $amount, $actor) {
            $permission->update([
                'status' => Permission::STATUS_REJECTED,
                'penalty_amount' => $amount,
                'is_double_penalty' => false,
            ]);

            $permission = $permission->refresh();
            $this->notifications->permissionDecided($permission, $actor);

            return $permission;
        });
    }

    /**
     * REJECTED → FORCED; penalty × double_penalty_multiplier; is_double_penalty = true.
     * Actor: owning employee or Manager (not Team Leader).
     */
    public function force(User $actor, Permission $permission): Permission
    {
        if (! $this->actorMayForce($actor, $permission)) {
            throw PermissionRequestException::cannotForce();
        }

        if ($permission->status !== Permission::STATUS_REJECTED) {
            throw PermissionRequestException::notRejected();
        }

        $base = (float) $permission->penalty_amount;
        if ($base <= 0) {
            $base = $this->penalties->amountForRejection($permission);
        }

        $permission->loadMissing('user');
        $multiplier = $permission->user !== null
            ? $this->penalties->doubleMultiplier($permission->user, $permission->type)
            : (float) config('penalties.double_penalty_multiplier', 2.0);
        $amount = round($base * $multiplier, 2);

        return DB::transaction(function () use ($permission, $amount, $actor) {
            $permission->update([
                'status' => Permission::STATUS_FORCED,
                'penalty_amount' => $amount,
                'is_double_penalty' => true,
            ]);

            $permission = $permission->refresh();
            $this->notifications->permissionForced($permission, $actor);

            return $permission;
        });
    }

    public function actorMayDecide(User $actor, Permission $permission): bool
    {
        $permission->loadMissing('user');

        if ($actor->isManager()) {
            return true;
        }

        if (! $actor->isTeamLeader()) {
            return false;
        }

        return (int) $permission->team_leader_id === (int) $actor->id
            || (int) $permission->user?->team_leader_id === (int) $actor->id;
    }

    public function actorMayForce(User $actor, Permission $permission): bool
    {
        $permission->loadMissing('user');

        if ((int) $permission->user_id === (int) $actor->id) {
            return true;
        }

        if ($actor->isManager()) {
            return true;
        }

        if ($actor->isTeamLeader()) {
            return (int) $permission->team_leader_id === (int) $actor->id
                || (int) $permission->user?->team_leader_id === (int) $actor->id;
        }

        return false;
    }

    private function assertActorMayDecide(User $actor, Permission $permission): void
    {
        if (! $this->actorMayDecide($actor, $permission)) {
            throw PermissionRequestException::notTeamMember();
        }
    }

    private function assertPending(Permission $permission): void
    {
        if ($permission->status !== Permission::STATUS_PENDING) {
            throw PermissionRequestException::notPending();
        }
    }

    private function normalizeTime(string $time): string
    {
        return Carbon::parse($time)->format('H:i');
    }
}
