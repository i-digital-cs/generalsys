<?php

namespace App\Notifications;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Notifications\Notification;

/** Supervisor closed an open punch at actual time. */
class AttendanceForceClockOutNotification extends Notification
{
    public function __construct(
        public Attendance $attendance,
        public User $actor,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $this->attendance->loadMissing('user');
        $employee = $this->attendance->user;
        $tz = config('app.timezone');
        $date = $this->attendance->date->toDateString();

        return [
            'kind' => 'attendance_force_clock_out',
            'attendance_id' => $this->attendance->id,
            'employee_id' => $employee?->id,
            'employee_name_ar' => $employee?->name_ar,
            'employee_name_en' => $employee?->name_en,
            'actor_name_ar' => $this->actor->name_ar,
            'actor_name_en' => $this->actor->name_en,
            'date' => $date,
            'shift_type' => $this->attendance->shift_type,
            'clock_out' => $this->attendance->clock_out?->timezone($tz)->format('Y-m-d H:i'),
            'url' => $this->urlFor($notifiable, $employee?->id, $date),
        ];
    }

    private function urlFor(object $notifiable, ?int $employeeId, string $date): string
    {
        if ($notifiable instanceof User && (int) $notifiable->id === (int) ($employeeId ?? 0)) {
            return route('employee.report', ['date' => $date]);
        }

        if ($notifiable instanceof User && ($notifiable->isTeamLeader() || $notifiable->canManageOperations())) {
            return route('team-leader.attendance', [
                'employee_id' => $employeeId,
                'from' => $date,
                'to' => $date,
            ]);
        }

        return route('dashboard');
    }
}
