<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vacation;
use App\Services\Vacations\VacationRequestService;

/**
 * Vacation requests (ANNUAL / SICK / EMERGENCY).
 * Mirrors VacationRequestService::actorMayDecide / actorMayForce — no widening.
 */
class VacationPolicy
{
    public function __construct(
        private readonly VacationRequestService $vacations,
    ) {}

    /** Owner may view their own request. */
    public function view(User $user, Vacation $vacation): bool
    {
        return (int) $vacation->user_id === (int) $user->id
            || $this->vacations->actorMayDecide($user, $vacation);
    }

    /** TL (team) or Manager — approve/reject PENDING. */
    public function decide(User $user, Vacation $vacation): bool
    {
        return $this->vacations->actorMayDecide($user, $vacation);
    }

    /** Self, TL (team), or Manager — REJECTED → FORCED. */
    public function force(User $user, Vacation $vacation): bool
    {
        return $this->vacations->actorMayForce($user, $vacation);
    }
}
