<?php

namespace App\Services\Permissions;

use App\Exceptions\PermissionRequestException;
use App\Models\Permission;
use App\Models\User;
use App\Services\Approvals\ApprovalsListFilters;
use App\Services\Employees\TeamMembershipService;
use App\Services\Hr\HrRequestWriteGuard;
use App\Services\Notifications\AppNotificationService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Leave/late/absence permission requests (T-2.7–T-2.9, T-15.1).
 * Submit: any role (self). Employees need ≥1 team leader; TL/Manager may omit (Admin decides).
 * Queue/decide: either membership TL, or Manager. Force: self, TL (team), or Manager.
 * Absence accept/force writes attendance EXCUSED_ABSENCE for payroll day counts.
 * Penalty amounts never exposed on TL UI.
 */
class PermissionRequestService
{
    /** Sentinel times for ABSENCE_PERMISSION (schema times NOT NULL; UI hides them). */
    public const ABSENCE_FROM = '00:00';

    public const ABSENCE_TO = '23:59';

    public function __construct(
        private readonly PermissionPenaltyCalculator $penalties,
        private readonly AppNotificationService $notifications,
        private readonly TeamMembershipService $membership,
        private readonly AbsenceAttendanceApplier $absenceAttendance,
        private readonly HrRequestWriteGuard $writeGuard,
    ) {}

    /**
     * @param  array{type: string, request_date: string, end_date?: ?string, from_time?: ?string, to_time?: ?string, reason?: ?string}  $data
     */
    public function submit(User $user, array $data): Permission
    {
        if ($user->isEmployee() && $this->membership->leaderIds($user) === []) {
            throw PermissionRequestException::noTeamLeader();
        }

        $type = (string) $data['type'];
        $date = Carbon::parse($data['request_date'])->toDateString();
        $endDate = $date;

        if ($type === Permission::TYPE_ABSENCE) {
            $from = self::ABSENCE_FROM;
            $to = self::ABSENCE_TO;
            $endDate = Carbon::parse($data['end_date'] ?? $date)->toDateString();
            if ($endDate < $date) {
                throw PermissionRequestException::invalidAbsencePeriod();
            }
            $days = Carbon::parse($date)->diffInDays(Carbon::parse($endDate)) + 1;
            if ($days > 31) {
                throw PermissionRequestException::absencePeriodTooLong();
            }
            $this->assertNoOverlappingAbsence($user, $date, $endDate);
        } else {
            $from = $this->normalizeTime((string) ($data['from_time'] ?? ''));
            $to = $this->normalizeTime((string) ($data['to_time'] ?? ''));
            if ($to <= $from) {
                throw PermissionRequestException::invalidTimeRange();
            }
        }

        return DB::transaction(function () use ($user, $data, $type, $date, $endDate, $from, $to) {
            $permission = Permission::query()->create([
                'user_id' => $user->id,
                'type' => $type,
                'request_date' => $date,
                'end_date' => $endDate,
                'from_time' => $from,
                'to_time' => $to,
                'reason' => $data['reason'] ?? null,
                'status' => Permission::STATUS_PENDING,
                'team_leader_id' => $this->submitSnapshotLeaderId($user),
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
            ->with('decidedBy')
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
    public function listForActor(
        User $actor,
        ApprovalsListFilters|string $filters = 'pending',
        int $perPage = 25,
    ): LengthAwarePaginator {
        $perPage = min(max($perPage, 1), 50);
        $filters = $filters instanceof ApprovalsListFilters
            ? $filters
            : ApprovalsListFilters::fromStatus($filters);

        $query = Permission::query()->with(['user.teamLeaders', 'decidedBy']);

        if ($actor->isTeamLeader()) {
            $query->whereHas('user', fn ($q) => $q->where('role', User::ROLE_EMPLOYEE)->ledBy($actor->id));
        } elseif (! $actor->isManager()) {
            abort(403);
        }

        match ($filters->status) {
            'pending' => $query->where('status', Permission::STATUS_PENDING)
                ->orderBy('request_date')
                ->orderBy('id'),
            'approved' => $query->whereIn('status', [
                Permission::STATUS_ACCEPTED,
                Permission::STATUS_FORCED,
            ])
                ->orderByDesc('request_date')
                ->orderByDesc('id'),
            'rejected' => $query->where('status', Permission::STATUS_REJECTED)
                ->orderByDesc('request_date')
                ->orderByDesc('id'),
            'decided' => $query->whereIn('status', [
                Permission::STATUS_ACCEPTED,
                Permission::STATUS_REJECTED,
                Permission::STATUS_FORCED,
            ])
                ->orderByDesc('request_date')
                ->orderByDesc('id'),
            default => $query->orderByDesc('request_date')->orderByDesc('id'),
        };

        $filters->applyEmployeeFilters($query);
        $filters->applyDateMonth($query, 'request_date');

        return $query->paginate($perPage, ['*'], 'perm_page')->withQueryString();
    }

    public function approve(User $actor, Permission $permission): Permission
    {
        $this->assertActorMayDecide($actor, $permission);

        return DB::transaction(function () use ($permission, $actor) {
            $permission = $this->writeGuard->lockPendingPermission($permission);

            if ($permission->isAbsence()) {
                $this->absenceAttendance->apply($permission);
            }

            $permission->update([
                'status' => Permission::STATUS_ACCEPTED,
                'penalty_amount' => 0,
                'is_double_penalty' => false,
                'decided_by' => $actor->id,
            ]);

            $permission = $permission->refresh();
            $this->notifications->permissionDecided($permission, $actor);

            return $permission;
        });
    }

    public function reject(User $actor, Permission $permission): Permission
    {
        $this->assertActorMayDecide($actor, $permission);

        return DB::transaction(function () use ($permission, $actor) {
            $permission = $this->writeGuard->lockPendingPermission($permission);
            $amount = $this->penalties->amountForRejection($permission);

            $permission->update([
                'status' => Permission::STATUS_REJECTED,
                'penalty_amount' => $amount,
                'is_double_penalty' => false,
                'decided_by' => $actor->id,
            ]);

            $permission = $permission->refresh();
            $this->notifications->permissionDecided($permission, $actor);

            return $permission;
        });
    }

    /**
     * REJECTED → FORCED; penalty × double_penalty_multiplier; is_double_penalty = true.
     * Absence also marks the day EXCUSED_ABSENCE for payroll.
     */
    public function force(User $actor, Permission $permission): Permission
    {
        if (! $this->actorMayForce($actor, $permission)) {
            throw PermissionRequestException::cannotForce();
        }

        return DB::transaction(function () use ($permission, $actor) {
            $permission = $this->writeGuard->lockRejectedPermission($permission);

            $base = (float) $permission->penalty_amount;
            if ($base <= 0) {
                $base = $this->penalties->amountForRejection($permission);
            }

            $permission->loadMissing('user');
            $multiplier = $permission->user !== null
                ? $this->penalties->doubleMultiplier($permission->user, $permission->type)
                : (float) config('penalties.double_penalty_multiplier', 2.0);
            $amount = round($base * $multiplier, 2);

            if ($permission->isAbsence()) {
                $this->absenceAttendance->apply($permission);
            }

            $permission->update([
                'status' => Permission::STATUS_FORCED,
                'penalty_amount' => $amount,
                'is_double_penalty' => true,
                'decided_by' => $actor->id,
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

        return $this->teamLeaderLeadsOwner($actor, $permission->user);
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

        return $this->teamLeaderLeadsOwner($actor, $permission->user);
    }

    private function assertNoOverlappingAbsence(User $user, string $start, string $end): void
    {
        $exists = Permission::query()
            ->where('user_id', $user->id)
            ->where('type', Permission::TYPE_ABSENCE)
            ->whereIn('status', [
                Permission::STATUS_PENDING,
                Permission::STATUS_ACCEPTED,
                Permission::STATUS_FORCED,
            ])
            ->where(function ($q) use ($start, $end): void {
                $q->whereDate('request_date', '<=', $end)
                    ->where(function ($inner) use ($start): void {
                        $inner->whereDate('end_date', '>=', $start)
                            ->orWhere(function ($legacy) use ($start): void {
                                // Pre-migration rows may lack end_date until backfill.
                                $legacy->whereNull('end_date')->whereDate('request_date', '>=', $start);
                            });
                    });
            })
            ->exists();

        if ($exists) {
            throw PermissionRequestException::duplicateAbsence();
        }
    }

    private function teamLeaderLeadsOwner(User $actor, ?User $owner): bool
    {
        return $actor->isTeamLeader()
            && $owner instanceof User
            && $owner->isEmployee()
            && $this->membership->leads($actor, $owner);
    }

    private function submitSnapshotLeaderId(User $user): ?int
    {
        if (! $user->isEmployee()) {
            return null;
        }

        return $this->membership->leaderIds($user)[0] ?? null;
    }

    private function assertActorMayDecide(User $actor, Permission $permission): void
    {
        if (! $this->actorMayDecide($actor, $permission)) {
            throw PermissionRequestException::notTeamMember();
        }
    }

    private function normalizeTime(string $time): string
    {
        return Carbon::parse($time)->format('H:i');
    }
}
