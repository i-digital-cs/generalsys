<?php

namespace App\Services\Notifications;

use App\Models\Attendance;
use App\Models\Permission;
use App\Models\User;
use App\Models\Vacation;
use App\Notifications\AttendanceAutoClockOutNotification;
use App\Notifications\AttendanceGeofenceWarningNotification;
use App\Notifications\DailyReportMissingNotification;
use App\Notifications\DailyReportRepliedNotification;
use App\Notifications\DailyReportSubmittedNotification;
use App\Notifications\PermissionDecisionNotification;
use App\Notifications\PermissionSubmittedNotification;
use App\Notifications\VacationDecisionNotification;
use App\Notifications\VacationSubmittedNotification;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * In-app database notifications (Hostinger-safe; no websockets).
 * Managers are CC'd on every permission/report action.
 */
class AppNotificationService
{
    public function vacationSubmitted(Vacation $vacation): void
    {
        $vacation->loadMissing('user');
        $notification = new VacationSubmittedNotification($vacation);

        $leaderId = $vacation->team_leader_id ?? $vacation->user?->team_leader_id;
        if ($leaderId !== null) {
            User::query()->find($leaderId)?->notify($notification);
        }

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

        if ($actor === null || ! $actor->isTeamLeader()) {
            $this->notifyTeamLeaderOfVacation($vacation, $notification, $actor);
        }

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

        $this->notifyTeamLeaderOfVacation($vacation, $notification, $actor);
        $this->notifyManagers($notification, $actor);
    }

    public function permissionSubmitted(Permission $permission): void
    {
        $permission->loadMissing('user');
        $notification = new PermissionSubmittedNotification($permission);

        $this->notifyTeamLeaderOf($permission, $notification);
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

        if ($actor === null || ! $actor->isTeamLeader()) {
            $this->notifyTeamLeaderOf($permission, $notification, $actor);
        }

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

        $this->notifyTeamLeaderOf($permission, $notification, $actor);
        $this->notifyManagers($notification, $actor);
    }

    public function reportSubmitted(Attendance $attendance): void
    {
        $attendance->loadMissing('user');
        $notification = new DailyReportSubmittedNotification($attendance);

        $leaderId = $attendance->user?->team_leader_id;
        if ($leaderId !== null) {
            User::query()->find($leaderId)?->notify($notification);
        }

        $this->notifyManagers($notification);
    }

    /**
     * Missing daily report warning: notify the person's team leader
     * (employees only) + all managers.
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

        if ($person->isEmployee() && $person->team_leader_id !== null) {
            User::query()->find($person->team_leader_id)?->notify($notification);
            $sent++;
        }

        $managers = $this->activeManagers();
        foreach ($managers as $manager) {
            $manager->notify($notification);
        }

        return $sent + $managers->count();
    }

    public function reportReplied(Attendance $attendance, ?User $actor = null): void
    {
        $attendance->loadMissing('user');
        $notification = new DailyReportRepliedNotification($attendance);

        if ($attendance->user !== null) {
            $attendance->user->notify($notification);
        }

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

        $leaderId = $attendance->user?->team_leader_id;
        if ($leaderId !== null) {
            User::query()->find($leaderId)?->notify($notification);
        }

        $this->notifyManagers($notification);
    }

    /**
     * Soft geofence: punch accepted outside office — notify employee + TL + managers.
     */
    public function attendanceGeofenceWarning(
        Attendance $attendance,
        string $kindPunch,
        int $distanceM,
        int $radiusM,
    ): void {
        $attendance->loadMissing('user');
        $notification = new AttendanceGeofenceWarningNotification(
            $attendance,
            $kindPunch,
            $distanceM,
            $radiusM,
        );

        if ($attendance->user !== null) {
            $attendance->user->notify($notification);
        }

        $leaderId = $attendance->user?->team_leader_id;
        if ($leaderId !== null) {
            User::query()->find($leaderId)?->notify($notification);
        }

        $this->notifyManagers($notification);
    }

    private function notifyTeamLeaderOf(
        Permission $permission,
        object $notification,
        ?User $except = null,
    ): void {
        $leaderId = $permission->team_leader_id ?? $permission->user?->team_leader_id;

        if ($leaderId === null) {
            return;
        }

        if ($except !== null && (int) $except->id === (int) $leaderId) {
            return;
        }

        User::query()->find($leaderId)?->notify($notification);
    }

    private function notifyTeamLeaderOfVacation(
        Vacation $vacation,
        object $notification,
        ?User $except = null,
    ): void {
        $leaderId = $vacation->team_leader_id ?? $vacation->user?->team_leader_id;

        if ($leaderId === null) {
            return;
        }

        if ($except !== null && (int) $except->id === (int) $leaderId) {
            return;
        }

        User::query()->find($leaderId)?->notify($notification);
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
}
