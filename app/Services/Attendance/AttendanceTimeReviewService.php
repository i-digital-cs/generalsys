<?php

namespace App\Services\Attendance;

use App\Models\Attendance;
use App\Models\User;
use App\Services\Employees\TeamMembershipService;
use App\Services\Notifications\AppNotificationService;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * TL/Admin manual credited hours, late override, overtime recommendation (T-9.1).
 */
class AttendanceTimeReviewService
{
    public function __construct(
        private readonly TeamMembershipService $membership,
        private readonly AttendanceTimeCalculator $calculator,
        private readonly AttendanceService $attendanceService,
        private readonly AppNotificationService $notifications,
    ) {}

    public function actorMayReview(User $actor, Attendance $attendance): bool
    {
        $attendance->loadMissing('user');
        $owner = $attendance->user;
        if ($owner === null) {
            return false;
        }

        if ($actor->canManageOperations()) {
            return true;
        }

        return $actor->isTeamLeader() && $this->membership->leads($actor, $owner);
    }

    /**
     * @param  array{credited_hours?: float|null, adjusted_late_minutes?: int|null}  $data
     */
    public function applyAdjustments(User $actor, Attendance $attendance, array $data): Attendance
    {
        if (! $this->actorMayReview($actor, $attendance)) {
            throw ValidationException::withMessages(['attendance' => __('attendance_review.errors.forbidden')]);
        }

        $payload = [];
        if (array_key_exists('credited_hours', $data)) {
            $payload['credited_hours'] = $data['credited_hours'] === null || $data['credited_hours'] === ''
                ? null
                : round((float) $data['credited_hours'], 2);
        }
        if (array_key_exists('adjusted_late_minutes', $data)) {
            $payload['adjusted_late_minutes'] = $data['adjusted_late_minutes'] === null || $data['adjusted_late_minutes'] === ''
                ? null
                : max(0, (int) $data['adjusted_late_minutes']);
        }

        if ($payload !== []) {
            $attendance->update($payload);
        }

        return $this->calculator->recalculate($attendance->fresh(['segments', 'user']), $this->attendanceService);
    }

    /** @return Collection<int, Attendance> */
    public function listOvertimeForActor(User $actor, string $filter = 'pending'): Collection
    {
        $filter = in_array($filter, ['pending', 'decided', 'all'], true) ? $filter : 'pending';

        if (! $actor->isTeamLeader() && ! $actor->canManageOperations()) {
            return collect();
        }

        $query = Attendance::query()
            ->with(['user', 'overtimeTlDecider', 'overtimeAdminDecider'])
            ->whereNotNull('clock_out')
            ->orderByDesc('date')
            ->orderByDesc('id');

        if ($actor->isTeamLeader() && ! $actor->canManageOperations()) {
            $employeeIds = $this->membership->visibleEmployeeIds($actor);
            if ($employeeIds === []) {
                return collect();
            }
            $query->whereIn('user_id', $employeeIds);
        }

        match ($filter) {
            'pending' => $actor->canManageOperations()
                ? $query->where('overtime_tl_recommendation', 'RECOMMEND')
                    ->where('overtime_admin_decision', 'PENDING')
                : $query->where('overtime_tl_recommendation', 'NONE')
                    ->whereColumn('raw_actual_hours', '>', 'planned_hours'),
            'decided' => $query->where(function ($q) {
                $q->where('overtime_tl_recommendation', 'NO_OVERTIME')
                    ->orWhereIn('overtime_admin_decision', ['APPROVED', 'REJECTED']);
            }),
            default => $query->where(function ($q) {
                $q->whereColumn('raw_actual_hours', '>', 'planned_hours')
                    ->orWhere('overtime_tl_recommendation', '!=', 'NONE');
            }),
        };

        return $query->get()->filter(fn (Attendance $row) => $this->actorMayReview($actor, $row))->values();
    }

    public function tlRecommendOvertime(User $actor, Attendance $attendance, int $minutes, string $recommendation): Attendance
    {
        if (! $actor->isTeamLeader() || ! $this->actorMayReview($actor, $attendance)) {
            throw ValidationException::withMessages(['attendance' => __('attendance_review.errors.forbidden')]);
        }

        if (! in_array($recommendation, ['RECOMMEND', 'NO_OVERTIME'], true)) {
            throw ValidationException::withMessages(['overtime' => __('attendance_review.errors.invalid_overtime')]);
        }

        $attendance->update([
            'recommended_overtime_minutes' => max(0, $minutes),
            'overtime_tl_recommendation' => $recommendation,
            'overtime_tl_by' => $actor->id,
            'overtime_tl_at' => now(),
            'overtime_admin_decision' => 'PENDING',
            'overtime_admin_by' => null,
            'overtime_admin_at' => null,
        ]);

        $fresh = $this->calculator->recalculate($attendance->fresh(['segments', 'user']), $this->attendanceService);
        $this->notifications->attendanceOvertimeTlRecommended($fresh);

        return $fresh;
    }

    public function adminDecideOvertime(User $actor, Attendance $attendance, string $decision): Attendance
    {
        if (! $actor->canManageOperations()) {
            throw ValidationException::withMessages(['attendance' => __('attendance_review.errors.forbidden')]);
        }

        if (! in_array($decision, ['APPROVED', 'REJECTED'], true)) {
            throw ValidationException::withMessages(['overtime' => __('attendance_review.errors.invalid_overtime')]);
        }

        if ($attendance->overtime_tl_recommendation === 'NONE') {
            throw ValidationException::withMessages(['overtime' => __('attendance_review.errors.no_tl_overtime')]);
        }

        $attendance->update([
            'overtime_admin_decision' => $decision,
            'overtime_admin_by' => $actor->id,
            'overtime_admin_at' => now(),
        ]);

        $fresh = $this->calculator->recalculate($attendance->fresh(['segments', 'user']), $this->attendanceService);
        $this->notifications->attendanceOvertimeAdminDecided($fresh);

        return $fresh;
    }
}
