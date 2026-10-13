<?php

namespace App\Services\Attendance;

use App\Models\Attendance;
use App\Models\User;
use App\Services\Approvals\ApprovalsListFilters;
use App\Services\Employees\TeamMembershipService;
use App\Services\Notifications\AppNotificationService;
use Illuminate\Pagination\LengthAwarePaginator;
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
        private readonly ShiftConfig $shifts,
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

    /**
     * @return LengthAwarePaginator<int, Attendance>
     */
    public function listOvertimeForActor(
        User $actor,
        ApprovalsListFilters|string $filters = 'pending',
        int $perPage = 25,
    ): LengthAwarePaginator {
        $perPage = min(max($perPage, 1), 50);
        $filters = $filters instanceof ApprovalsListFilters
            ? $filters
            : ApprovalsListFilters::fromStatus($filters);
        $status = $filters->status;

        if (! $actor->isTeamLeader() && ! $actor->canManageOperations()) {
            return new LengthAwarePaginator([], 0, $perPage, 1, [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'pageName' => 'ot_page',
            ]);
        }

        $query = Attendance::query()
            ->with(['user', 'overtimeTlDecider', 'overtimeAdminDecider', 'segments', 'gaps'])
            ->whereNotNull('clock_out')
            ->orderByDesc('date')
            ->orderByDesc('id');

        if ($actor->isTeamLeader() && ! $actor->canManageOperations()) {
            $employeeIds = $this->membership->visibleEmployeeIds($actor);
            if ($employeeIds === []) {
                return new LengthAwarePaginator([], 0, $perPage, 1, [
                    'path' => LengthAwarePaginator::resolveCurrentPath(),
                    'pageName' => 'ot_page',
                ]);
            }
            $query->whereIn('user_id', $employeeIds);
        }

        match ($status) {
            'pending' => $actor->canManageOperations()
                ? $query->where('overtime_tl_recommendation', 'RECOMMEND')
                    ->where('overtime_admin_decision', 'PENDING')
                : $query->where(function ($q) {
                    $q->where(function ($first) {
                        $first->where('overtime_tl_recommendation', 'NONE')
                            ->where('raw_actual_hours', '>', 0)
                            ->whereRaw('raw_actual_hours > COALESCE(planned_hours, 0)');
                    })->orWhere(function ($rejected) {
                        $rejected->where('overtime_tl_recommendation', 'RECOMMEND')
                            ->where('overtime_admin_decision', 'REJECTED');
                    });
                }),
            'approved' => $query->where('overtime_admin_decision', 'APPROVED'),
            'rejected' => $query->where(function ($q) {
                $q->where('overtime_admin_decision', 'REJECTED')
                    ->orWhere('overtime_tl_recommendation', 'NO_OVERTIME');
            }),
            'decided' => $query->where(function ($q) {
                $q->where('overtime_tl_recommendation', 'NO_OVERTIME')
                    ->orWhere('overtime_admin_decision', 'APPROVED');
            }),
            default => $query->where(function ($q) {
                $q->where(function ($live) {
                    $live->where('raw_actual_hours', '>', 0)
                        ->whereRaw('raw_actual_hours > COALESCE(planned_hours, 0)');
                })->orWhere('overtime_tl_recommendation', '!=', 'NONE');
            }),
        };

        $filters->applyEmployeeFilters($query);
        $filters->applyDateMonth($query, 'date');

        $rows = $query->limit(1000)->get()
            ->filter(function (Attendance $row) use ($actor, $status): bool {
                if (! $this->actorMayReview($actor, $row)) {
                    return false;
                }

                if (in_array($status, ['approved', 'rejected', 'decided'], true)) {
                    return true;
                }

                if ($status === 'pending' && $actor->canManageOperations()) {
                    return true;
                }

                $hasLiveOvertime = $this->shifts->overtimeDeltaMinutes($row) > 0;
                $awaitingTlAfterReject = $row->overtime_tl_recommendation === 'RECOMMEND'
                    && $row->overtime_admin_decision === 'REJECTED';

                if ($status === 'pending') {
                    return $awaitingTlAfterReject
                        || ($hasLiveOvertime && $row->overtime_tl_recommendation === 'NONE');
                }

                // all
                return $hasLiveOvertime
                    || $row->overtime_tl_recommendation !== 'NONE'
                    || $awaitingTlAfterReject;
            })
            ->values();

        $page = max(1, (int) request()->input('ot_page', 1));

        return new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'query' => request()->query(),
                'pageName' => 'ot_page',
            ],
        );
    }

    public function tlRecommendOvertime(User $actor, Attendance $attendance, int $minutes, string $recommendation): Attendance
    {
        if (! $actor->isTeamLeader() || ! $this->actorMayReview($actor, $attendance)) {
            throw ValidationException::withMessages(['attendance' => __('attendance_review.errors.forbidden')]);
        }

        if (! in_array($recommendation, ['RECOMMEND', 'NO_OVERTIME'], true)) {
            throw ValidationException::withMessages(['overtime' => __('attendance_review.errors.invalid_overtime')]);
        }

        if ($attendance->overtime_admin_decision === 'APPROVED') {
            throw ValidationException::withMessages(['overtime' => __('attendance_review.errors.already_final')]);
        }

        $attendance->update([
            'recommended_overtime_minutes' => max(0, $minutes),
            'overtime_tl_recommendation' => $recommendation,
            'overtime_tl_by' => $actor->id,
            'overtime_tl_at' => now(),
            // Reopens Admin queue after a prior reject (two-way TL ↔ Admin).
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

        if ($attendance->overtime_admin_decision === 'APPROVED') {
            throw ValidationException::withMessages(['overtime' => __('attendance_review.errors.already_final')]);
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
