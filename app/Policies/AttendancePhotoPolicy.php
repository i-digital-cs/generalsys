<?php

namespace App\Policies;

use App\Models\AttendancePhoto;
use App\Models\User;
use App\Services\Attendance\HoursComparisonService;

/**
 * Punch photos — owner, team TL, or Manager (TL↔Admin yes, T-6.6).
 */
class AttendancePhotoPolicy
{
    public function __construct(
        private readonly HoursComparisonService $hours,
    ) {}

    public function view(User $authUser, AttendancePhoto $photo): bool
    {
        $owner = $photo->attendance?->user;

        if ($owner === null) {
            return false;
        }

        return $this->hours->actorMayView($authUser, $owner);
    }
}
