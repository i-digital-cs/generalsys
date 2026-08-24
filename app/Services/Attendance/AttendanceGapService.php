<?php

namespace App\Services\Attendance;

use App\Models\Attendance;
use App\Models\AttendanceGap;
use App\Models\User;
use App\Services\Employees\TeamMembershipService;
use App\Services\Notifications\AppNotificationService;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Mid-shift gaps + TL recommend / Admin final decision (T-9.1).
 */
class AttendanceGapService
{
    public function __construct(
        private readonly TeamMembershipService $membership,
        private readonly AppNotificationService $notifications,
    ) {}

    public function recordGap(Attendance $attendance, CarbonInterface $gapStart, CarbonInterface $gapEnd): AttendanceGap
    {
        $minutes = max(0, (int) $gapStart->diffInMinutes($gapEnd, false));

        $gap = AttendanceGap::query()->create([
            'attendance_id' => $attendance->id,
            'gap_start' => $gapStart,
            'gap_end' => $gapEnd,
            'minutes' => $minutes,
        ]);

        $attendance->loadMissing('user');
        if ($attendance->user !== null) {
            $this->notifications->attendanceGapPending($gap, $attendance->user);
        }

        return $gap;
    }

    /** @return Collection<int, AttendanceGap> */
    public function listForActor(User $actor, string $filter = 'pending'): Collection
    {
        $filter = in_array($filter, ['pending', 'decided', 'all'], true) ? $filter : 'pending';

        if (! $actor->isTeamLeader() && ! $actor->canManageOperations()) {
            return collect();
        }

        $query = AttendanceGap::query()
            ->with(['attendance.user', 'tlDecider', 'adminDecider'])
            ->orderByDesc('gap_start');

        if ($actor->isTeamLeader() && ! $actor->canManageOperations()) {
            $employeeIds = $this->membership->visibleEmployeeIds($actor);
            if ($employeeIds === []) {
                return collect();
            }
            $query->whereHas('attendance', fn ($q) => $q->whereIn('user_id', $employeeIds));
        }

        match ($filter) {
            'pending' => $actor->canManageOperations()
                ? $query->where('admin_decision', AttendanceGap::ADMIN_PENDING)
                    ->where('tl_recommendation', '!=', AttendanceGap::TL_PENDING)
                : $query->where('tl_recommendation', AttendanceGap::TL_PENDING),
            'decided' => $query->where('admin_decision', '!=', AttendanceGap::ADMIN_PENDING),
            default => null,
        };

        return $query->get()->filter(fn (AttendanceGap $gap) => $this->actorMayView($actor, $gap))->values();
    }

    public function actorMayView(User $actor, AttendanceGap $gap): bool
    {
        $gap->loadMissing('attendance.user');
        $owner = $gap->attendance?->user;
        if ($owner === null) {
            return false;
        }

        if ((int) $actor->id === (int) $owner->id) {
            return true;
        }

        if ($actor->canManageOperations()) {
            return true;
        }

        return $actor->isTeamLeader() && $this->membership->leads($actor, $owner);
    }

    public function tlRecommend(User $actor, AttendanceGap $gap, string $recommendation, ?string $note = null): AttendanceGap
    {
        if (! in_array($recommendation, [AttendanceGap::TL_APPROVE, AttendanceGap::TL_REJECT], true)) {
            throw ValidationException::withMessages(['recommendation' => __('attendance_gaps.errors.invalid_recommendation')]);
        }

        if (! $actor->isTeamLeader() || ! $this->actorMayView($actor, $gap)) {
            throw ValidationException::withMessages(['gap' => __('attendance_gaps.errors.forbidden')]);
        }

        $gap->update([
            'tl_recommendation' => $recommendation,
            'tl_decided_by' => $actor->id,
            'tl_decided_at' => now(),
            'tl_note' => $note,
        ]);

        $gap->loadMissing('attendance.user');
        $this->notifications->attendanceGapTlRecommended($gap);

        return $gap->refresh();
    }

    public function adminDecide(User $actor, AttendanceGap $gap, string $decision, ?string $note = null): AttendanceGap
    {
        if (! $actor->canManageOperations()) {
            throw ValidationException::withMessages(['gap' => __('attendance_gaps.errors.forbidden')]);
        }

        if (! in_array($decision, [AttendanceGap::ADMIN_APPROVED, AttendanceGap::ADMIN_REJECTED], true)) {
            throw ValidationException::withMessages(['decision' => __('attendance_gaps.errors.invalid_decision')]);
        }

        if ($gap->tl_recommendation === AttendanceGap::TL_PENDING) {
            throw ValidationException::withMessages(['gap' => __('attendance_gaps.errors.await_tl')]);
        }

        $gap->update([
            'admin_decision' => $decision,
            'admin_decided_by' => $actor->id,
            'admin_decided_at' => now(),
            'admin_note' => $note,
        ]);

        $gap->loadMissing('attendance.user');
        $this->notifications->attendanceGapAdminDecided($gap);

        return $gap->refresh();
    }
}
