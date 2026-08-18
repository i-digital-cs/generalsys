<?php

namespace App\Services\Vacations;

use App\Exceptions\VacationRequestException;
use App\Models\RestDay;
use App\Models\User;
use App\Models\Vacation;
use App\Services\Employees\RestDayService;
use App\Services\Employees\TeamMembershipService;
use App\Services\Notifications\AppNotificationService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Vacation requests — swap a rest day for the day off (product lock 2026-08-03).
 *
 * - Submit requires replace_rest_day_id (employee's unused rest date).
 * - Cost = that rest day (removed on approve). No separate vacation-credit UI.
 * - Reject: rest day stays available.
 * - Force (A): REJECTED → FORCED; keep rest day; off day still counts as vacation.
 * - Employee → either membership TL decides; TL/Manager → Manager decides.
 * - Force: self, TL (team), or Manager (same as leave/late).
 */
class VacationRequestService
{
    public function __construct(
        private readonly AppNotificationService $notifications,
        private readonly RestDayService $restDays,
        private readonly TeamMembershipService $membership,
    ) {}

    /** Inclusive calendar days between two Y-m-d dates. */
    public function suggestedDays(string $startDate, string $endDate): float
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->startOfDay();

        if ($end->lt($start)) {
            return 0.0;
        }

        return (float) ($start->diffInDays($end) + 1);
    }

    /**
     * @param  array{type: string, start_date: string, end_date?: string, days_requested?: float|int|string, replace_rest_day_id: int|string}  $data
     */
    public function submit(User $user, array $data): Vacation
    {
        $start = Carbon::parse($data['start_date'])->startOfDay();
        $end = Carbon::parse($data['end_date'] ?? $data['start_date'])->startOfDay();

        if (! $start->equalTo($end)) {
            throw VacationRequestException::mustBeSingleDay();
        }

        $days = round((float) ($data['days_requested'] ?? 1), 1);
        if ($days < 0.5) {
            throw VacationRequestException::invalidDays();
        }

        if ($this->hasOverlap($user, $start->toDateString(), $end->toDateString())) {
            throw VacationRequestException::overlapsExisting();
        }

        $restDay = $this->restDays->findOwned($user, (int) $data['replace_rest_day_id']);
        if ($restDay === null) {
            throw VacationRequestException::invalidRestDay();
        }

        if ($this->restDays->isReservedByPendingVacation($restDay)) {
            throw VacationRequestException::restDayReserved();
        }

        if ($restDay->date->toDateString() === $start->toDateString()) {
            throw VacationRequestException::cannotReplaceSameDay();
        }

        if ($user->isEmployee() && $this->membership->leaderIds($user) === []) {
            throw VacationRequestException::noTeamLeader();
        }

        $teamLeaderId = $user->isEmployee()
            ? ($this->membership->leaderIds($user)[0] ?? null)
            : null;

        return DB::transaction(function () use ($user, $data, $start, $end, $days, $restDay, $teamLeaderId) {
            $vacation = Vacation::query()->create([
                'user_id' => $user->id,
                'type' => $data['type'],
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'days_requested' => $days,
                'replace_rest_date' => $restDay->date->toDateString(),
                'replace_rest_day_id' => $restDay->id,
                'status' => Vacation::STATUS_PENDING,
                'team_leader_id' => $teamLeaderId,
            ]);

            $this->notifications->vacationSubmitted($vacation);

            return $vacation;
        });
    }

    public function listForUser(User $user, int $perPage = 25): LengthAwarePaginator
    {
        $perPage = min(max($perPage, 1), 50);

        return Vacation::query()
            ->with('decidedBy')
            ->where('user_id', $user->id)
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * @param  'pending'|'decided'|'all'  $filter
     */
    public function listForActor(User $actor, string $filter = 'pending', int $perPage = 25): LengthAwarePaginator
    {
        $perPage = min(max($perPage, 1), 50);
        $filter = in_array($filter, ['pending', 'decided', 'all'], true) ? $filter : 'pending';

        $query = Vacation::query()->with(['user.teamLeaders', 'decidedBy']);

        if ($actor->isTeamLeader()) {
            $query->whereHas('user', fn ($q) => $q->where('role', User::ROLE_EMPLOYEE)->ledBy($actor->id));
        } elseif (! $actor->isManager()) {
            abort(403);
        }

        if ($filter === 'pending') {
            $query->where('status', Vacation::STATUS_PENDING)
                ->orderBy('start_date')
                ->orderBy('id');
        } elseif ($filter === 'decided') {
            $query->whereIn('status', [
                Vacation::STATUS_APPROVED,
                Vacation::STATUS_REJECTED,
                Vacation::STATUS_FORCED,
            ])
                ->orderByDesc('start_date')
                ->orderByDesc('id');
        } else {
            $query->orderByDesc('start_date')->orderByDesc('id');
        }

        $page = $query->paginate($perPage, ['*'], 'vac_page')->withQueryString();
        $this->attachRestAvailability($page);

        return $page;
    }

    public function approve(User $actor, Vacation $vacation): Vacation
    {
        $this->assertActorMayDecide($actor, $vacation);
        $this->assertPending($vacation);

        return DB::transaction(function () use ($vacation, $actor) {
            $vacation->loadMissing(['user', 'replaceRestDay']);

            if ($vacation->replace_rest_day_id !== null) {
                $restDay = RestDay::query()->find($vacation->replace_rest_day_id);
                if ($restDay !== null) {
                    $restDay->delete();
                }
            }

            $vacation->update([
                'status' => Vacation::STATUS_APPROVED,
                'replace_rest_day_id' => null,
                'decided_by' => $actor->id,
            ]);
            $vacation = $vacation->refresh();

            $this->notifications->vacationDecided($vacation, $actor);

            return $vacation;
        });
    }

    public function reject(User $actor, Vacation $vacation): Vacation
    {
        $this->assertActorMayDecide($actor, $vacation);
        $this->assertPending($vacation);

        return DB::transaction(function () use ($vacation, $actor) {
            // Keep replace_rest_date for history; clear FK so rest day can be reused.
            $vacation->update([
                'status' => Vacation::STATUS_REJECTED,
                'replace_rest_day_id' => null,
                'decided_by' => $actor->id,
            ]);
            $vacation = $vacation->refresh();

            $this->notifications->vacationDecided($vacation, $actor);

            return $vacation;
        });
    }

    /**
     * REJECTED → FORCED (product A): take the day off; rest day is NOT consumed.
     */
    public function force(User $actor, Vacation $vacation): Vacation
    {
        if (! $this->actorMayForce($actor, $vacation)) {
            throw VacationRequestException::cannotForce();
        }

        if ($vacation->status !== Vacation::STATUS_REJECTED) {
            throw VacationRequestException::notRejected();
        }

        return DB::transaction(function () use ($vacation, $actor) {
            $vacation->update([
                'status' => Vacation::STATUS_FORCED,
                'replace_rest_day_id' => null,
                'decided_by' => $actor->id,
            ]);
            $vacation = $vacation->refresh();

            $this->notifications->vacationForced($vacation, $actor);

            return $vacation;
        });
    }

    public function actorMayDecide(User $actor, Vacation $vacation): bool
    {
        $vacation->loadMissing('user');

        if ($actor->isManager()) {
            return true;
        }

        return $this->teamLeaderLeadsOwner($actor, $vacation->user);
    }

    public function actorMayForce(User $actor, Vacation $vacation): bool
    {
        $vacation->loadMissing('user');

        if ((int) $vacation->user_id === (int) $actor->id) {
            return true;
        }

        if ($actor->isManager()) {
            return true;
        }

        return $this->teamLeaderLeadsOwner($actor, $vacation->user);
    }

    public function hasOverlap(User $user, string $startDate, string $endDate, ?int $exceptId = null): bool
    {
        $query = Vacation::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [
                Vacation::STATUS_PENDING,
                Vacation::STATUS_APPROVED,
                Vacation::STATUS_FORCED,
            ])
            ->whereDate('start_date', '<=', $endDate)
            ->whereDate('end_date', '>=', $startDate);

        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        return $query->exists();
    }

    private function teamLeaderLeadsOwner(User $actor, ?User $owner): bool
    {
        return $actor->isTeamLeader()
            && $owner instanceof User
            && $owner->isEmployee()
            && $this->membership->leads($actor, $owner);
    }

    private function assertActorMayDecide(User $actor, Vacation $vacation): void
    {
        if (! $this->actorMayDecide($actor, $vacation)) {
            throw VacationRequestException::notTeamMember();
        }
    }

    private function assertPending(Vacation $vacation): void
    {
        if ($vacation->status !== Vacation::STATUS_PENDING) {
            throw VacationRequestException::notPending();
        }
    }

    /** Attach available rest-day count for Approvals UI. */
    private function attachRestAvailability(LengthAwarePaginator $page): void
    {
        $rows = $page->getCollection();
        if ($rows->isEmpty()) {
            return;
        }

        $rows->transform(function (Vacation $vacation) {
            $available = $this->restDays->availableForSwap(
                $vacation->user ?? User::query()->findOrFail($vacation->user_id),
            )->count();

            $vacation->setAttribute('rest_days_available', $available);
            $vacation->setAttribute(
                'replace_rest_date_label',
                $vacation->replace_rest_date?->toDateString(),
            );

            return $vacation;
        });
    }
}
