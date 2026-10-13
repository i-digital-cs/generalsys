<?php

namespace App\Services\Attendance;

use App\Models\Attendance;
use App\Models\AttendanceGap;
use App\Models\User;
use App\Services\Approvals\ApprovalsListFilters;
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
    public function listForActor(User $actor, ApprovalsListFilters|string $filters = 'pending'): Collection
    {
        $filters = $filters instanceof ApprovalsListFilters
            ? $filters
            : ApprovalsListFilters::fromStatus($filters);

        if (! $actor->isTeamLeader() && ! $actor->canManageOperations()) {
            return collect();
        }

        $query = AttendanceGap::query()
            ->with(['attendance.user.teamLeaders', 'tlDecider', 'adminDecider'])
            ->orderByDesc('gap_start');

        if ($actor->isTeamLeader() && ! $actor->canManageOperations()) {
            $employeeIds = $this->membership->visibleEmployeeIds($actor);
            if ($employeeIds === []) {
                return collect();
            }
            $query->whereHas('attendance', fn ($q) => $q->whereIn('user_id', $employeeIds));
        }

        match ($filters->status) {
            'pending' => $actor->canManageOperations()
                ? $query->where('admin_decision', AttendanceGap::ADMIN_PENDING)
                    ->where('tl_recommendation', '!=', AttendanceGap::TL_PENDING)
                : $query->where(function ($q) {
                    $q->where('tl_recommendation', AttendanceGap::TL_PENDING)
                        ->orWhere('admin_decision', AttendanceGap::ADMIN_REJECTED);
                }),
            'approved', 'decided' => $query->where('admin_decision', AttendanceGap::ADMIN_APPROVED),
            'rejected' => $query->where('admin_decision', AttendanceGap::ADMIN_REJECTED),
            default => null,
        };

        if ($filters->userId !== null) {
            $query->whereHas('attendance', fn ($q) => $q->where('user_id', $filters->userId));
        }

        if ($filters->q !== '') {
            $like = '%'.$filters->q.'%';
            $query->whereHas('attendance.user', function ($user) use ($like): void {
                $user->where(function ($inner) use ($like): void {
                    $inner->where('name_ar', 'like', $like)
                        ->orWhere('name_en', 'like', $like)
                        ->orWhere('email', 'like', $like);
                });
            });
        }

        $start = $filters->monthStart();
        $end = $filters->monthEnd();
        if ($start !== null && $end !== null) {
            $query->whereBetween('gap_start', [$start->startOfDay(), $end->endOfDay()]);
        }

        return $query->limit(200)->get()
            ->filter(fn (AttendanceGap $gap) => $this->actorMayView($actor, $gap))
            ->values();
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

        if ($gap->admin_decision === AttendanceGap::ADMIN_APPROVED) {
            throw ValidationException::withMessages(['gap' => __('attendance_gaps.errors.already_final')]);
        }

        $gap->update([
            'tl_recommendation' => $recommendation,
            'tl_decided_by' => $actor->id,
            'tl_decided_at' => now(),
            'tl_note' => $note,
            // Reopens Admin queue after a prior reject (two-way TL ↔ Admin).
            'admin_decision' => AttendanceGap::ADMIN_PENDING,
            'admin_decided_by' => null,
            'admin_decided_at' => null,
            'admin_note' => null,
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

        if ($gap->admin_decision === AttendanceGap::ADMIN_APPROVED) {
            throw ValidationException::withMessages(['gap' => __('attendance_gaps.errors.already_final')]);
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
