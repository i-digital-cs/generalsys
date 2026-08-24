<?php

namespace App\Services\Notifications;

use App\Models\Attendance;
use App\Models\AttendanceGap;
use App\Models\Permission;
use App\Models\User;
use App\Models\Vacation;
use App\Notifications\AttendanceAutoClockOutNotification;
use App\Notifications\AttendanceGapNotification;
use App\Notifications\AttendanceGeofenceWarningNotification;
use App\Notifications\AttendanceOutsideShiftWindowNotification;
use App\Notifications\AttendanceOvertimeNotification;
use App\Notifications\DailyReportMissingNotification;
use App\Notifications\DailyReportRepliedNotification;
use App\Notifications\DailyReportSubmittedNotification;
use App\Notifications\PermissionDecisionNotification;
use App\Notifications\PermissionSubmittedNotification;
use App\Notifications\VacationDecisionNotification;
use App\Notifications\VacationSubmittedNotification;
use App\Services\Employees\TeamMembershipService;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * In-app database notifications (Hostinger-safe; no websockets).
 * Membership TLs (max two) are notified on employee events; managers are CC'd.
 */
class AppNotificationService
{
    public function __construct(private readonly TeamMembershipService $membership) {}

    public function vacationSubmitted(Vacation $vacation): void
    {
        $vacation->loadMissing('user');
        $notification = new VacationSubmittedNotification($vacation);

        $this->notifyMembershipLeaders($vacation->user, $notification);
        $this->notifyManagers($notification);
    }

    public function vacationDecided(Vacation $vacation, ?User $actor = null): void
    {
        $vacation->loadMissing('user');
        $notification = new VacationDecisionNotification($vacation);

        if ($vacation->user !== null
            && ($actor === null || (int) $vacation->user->id !== (int) $actor->id)
        ) {
            $vacation->user->notify($notification);
        }

        $this->notifyMembershipLeaders($vacation->user, $notification, $actor);
        $this->notifyManagers($notification, $actor);
    }

    /**
     * Force vacation: notify employee + TL + managers (skip actor).
     */
    public function vacationForced(Vacation $vacation, ?User $actor = null): void
    {
        $vacation->loadMissing('user');
        $notification = new VacationDecisionNotification($vacation);

        if ($vacation->user !== null
            && ($actor === null || (int) $vacation->user->id !== (int) $actor->id)
        ) {
            $vacation->user->notify($notification);
        }

        $this->notifyMembershipLeaders($vacation->user, $notification, $actor);
        $this->notifyManagers($notification, $actor);
    }

    public function permissionSubmitted(Permission $permission): void
    {
        $permission->loadMissing('user');
        $notification = new PermissionSubmittedNotification($permission);

        $this->notifyMembershipLeaders($permission->user, $notification);
        $this->notifyManagers($notification);
    }

    public function permissionDecided(Permission $permission, ?User $actor = null): void
    {
        $permission->loadMissing('user');
        $notification = new PermissionDecisionNotification($permission);

        if ($permission->user !== null
            && ($actor === null || (int) $permission->user->id !== (int) $actor->id)
        ) {
            $permission->user->notify($notification);
        }

        $this->notifyMembershipLeaders($permission->user, $notification, $actor);
        $this->notifyManagers($notification, $actor);
    }

    /**
     * Force: always notify employee + team leader + all managers
     * (skip the actor so they are not notified of their own click).
     */
    public function permissionForced(Permission $permission, ?User $actor = null): void
    {
        $permission->loadMissing('user');
        $notification = new PermissionDecisionNotification($permission);

        if ($permission->user !== null
            && ($actor === null || (int) $permission->user->id !== (int) $actor->id)
        ) {
            $permission->user->notify($notification);
        }

        $this->notifyMembershipLeaders($permission->user, $notification, $actor);
        $this->notifyManagers($notification, $actor);
    }

    public function reportSubmitted(Attendance $attendance): void
    {
        $attendance->loadMissing('user');
        $notification = new DailyReportSubmittedNotification($attendance);

        $this->notifyMembershipLeaders($attendance->user, $notification);

        $this->notifyOperationsAdmins($notification);
    }

    /**
     * Missing daily report warning: notify the person's team leaders
     * (employees only) + all managers and assistants.
     *
     * @return int recipients notified
     */
    public function reportMissing(
        ?Attendance $attendance,
        User $person,
        CarbonInterface $date,
        string $kind,
    ): int {
        $notification = new DailyReportMissingNotification($attendance, $person, $date, $kind);
        $sent = 0;

        if ($person->isFieldStaff()) {
            $sent += $this->notifyMembershipLeaders($person, $notification);
        }

        $ops = $this->activeOperationsAdmins();
        foreach ($ops as $admin) {
            $admin->notify($notification);
        }

        return $sent + $ops->count();
    }

    public function reportReplied(Attendance $attendance, ?User $actor = null): void
    {
        $attendance->loadMissing('user');
        $notification = new DailyReportRepliedNotification($attendance);

        if ($attendance->user !== null) {
            $attendance->user->notify($notification);
        }

        $this->notifyMembershipLeaders($attendance->user, $notification, $actor);
        $this->notifyManagers($notification, $actor);
    }

    /**
     * System auto clock-out: notify employee + team leader + managers.
     */
    public function attendanceAutoClockOut(Attendance $attendance): void
    {
        $attendance->loadMissing('user');
        $notification = new AttendanceAutoClockOutNotification($attendance);

        if ($attendance->user !== null) {
            $attendance->user->notify($notification);
        }

        $this->notifyMembershipLeaders($attendance->user, $notification);

        $this->notifyOperationsAdmins($notification);
    }

    /**
     * Punch accepted outside planned window — notify employee + TL(s) + Manager/Assistant.
     */
    public function attendanceOutsideShiftWindow(
        Attendance $attendance,
        string $kindPunch,
        string $reason,
    ): void {
        $attendance->loadMissing('user');
        $notification = new AttendanceOutsideShiftWindowNotification(
            $attendance,
            $kindPunch,
            $reason,
        );

        if ($attendance->user !== null) {
            $attendance->user->notify($notification);
        }

        $this->notifyMembershipLeaders($attendance->user, $notification);
        $this->notifyOperationsAdmins($notification);
    }

    /**
     * Soft geofence: punch accepted outside office — notify employee + TL + managers + assistants.
     */
    public function attendanceGeofenceWarning(
        Attendance $attendance,
        string $kindPunch,
        int $distanceM,
        int $radiusM,
        ?float $lat = null,
        ?float $lng = null,
    ): void {
        $attendance->loadMissing('user');
        $notification = new AttendanceGeofenceWarningNotification(
            $attendance,
            $kindPunch,
            $distanceM,
            $radiusM,
            $lat,
            $lng,
        );

        if ($attendance->user !== null) {
            $attendance->user->notify($notification);
        }

        $this->notifyMembershipLeaders($attendance->user, $notification);

        $this->notifyOperationsAdmins($notification);
    }

    public function attendanceGapPending(AttendanceGap $gap, User $person): void
    {
        $notification = new AttendanceGapNotification($gap, 'attendance_gap_pending');
        $person->notify($notification);
        $this->notifyMembershipLeaders($person, $notification);
        $this->notifyOperationsAdmins($notification);
    }

    public function attendanceGapTlRecommended(AttendanceGap $gap): void
    {
        $gap->loadMissing('attendance.user');
        $notification = new AttendanceGapNotification($gap, 'attendance_gap_tl_decided');
        if ($gap->attendance?->user !== null) {
            $gap->attendance->user->notify($notification);
        }
        $this->notifyOperationsAdmins($notification);
    }

    public function attendanceGapAdminDecided(AttendanceGap $gap): void
    {
        $gap->loadMissing('attendance.user');
        $notification = new AttendanceGapNotification($gap, 'attendance_gap_admin_decided');
        if ($gap->attendance?->user !== null) {
            $gap->attendance->user->notify($notification);
        }
        $this->notifyMembershipLeaders($gap->attendance?->user, $notification);
    }

    public function attendanceOvertimeTlRecommended(Attendance $attendance): void
    {
        $attendance->loadMissing('user');
        $notification = new AttendanceOvertimeNotification($attendance, 'attendance_overtime_tl_decided');
        if ($attendance->user !== null) {
            $attendance->user->notify($notification);
        }
        $this->notifyOperationsAdmins($notification);
    }

    public function attendanceOvertimeAdminDecided(Attendance $attendance): void
    {
        $attendance->loadMissing('user');
        $notification = new AttendanceOvertimeNotification($attendance, 'attendance_overtime_admin_decided');
        if ($attendance->user !== null) {
            $attendance->user->notify($notification);
        }
        $this->notifyMembershipLeaders($attendance->user, $notification);
    }

    /**
     * Notify every TEAM_LEADER on the employee's membership (max two).
     *
     * @return int leaders notified
     */
    private function notifyMembershipLeaders(?User $person, object $notification, ?User $except = null): int
    {
        if ($person === null) {
            return 0;
        }

        $ids = $this->membership->leaderIds($person);
        if ($ids === []) {
            return 0;
        }

        $exceptId = $except !== null ? (int) $except->id : null;
        $sent = 0;

        foreach (User::query()->whereIn('id', $ids)->where('status', User::STATUS_ACTIVE)->get() as $leader) {
            if ($exceptId !== null && (int) $leader->id === $exceptId) {
                continue;
            }

            $leader->notify($notification);
            $sent++;
        }

        return $sent;
    }

    private function notifyManagers(object $notification, ?User $except = null): void
    {
        foreach ($this->activeManagers($except) as $manager) {
            $manager->notify($notification);
        }
    }

    /** @return Collection<int, User> */
    private function activeManagers(?User $except = null): Collection
    {
        $query = User::query()
            ->where('role', User::ROLE_MANAGER)
            ->where('status', User::STATUS_ACTIVE);

        if ($except !== null) {
            $query->where('id', '!=', $except->id);
        }

        return $query->get();
    }

    private function notifyOperationsAdmins(object $notification, ?User $except = null): void
    {
        foreach ($this->activeOperationsAdmins($except) as $admin) {
            $admin->notify($notification);
        }
    }

    /** @return Collection<int, User> */
    private function activeOperationsAdmins(?User $except = null): Collection
    {
        $query = User::query()
            ->whereIn('role', [User::ROLE_MANAGER, User::ROLE_ASSISTANT])
            ->where('status', User::STATUS_ACTIVE);

        if ($except !== null) {
            $query->where('id', '!=', $except->id);
        }

        return $query->get();
    }
}
