<?php

namespace App\Notifications;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Notifications\Notification;

/** Employee forgot to clock out — system closed after grace past expected end. */
class AttendanceAutoClockOutNotification extends Notification
{
    public function __construct(public Attendance $attendance) {}

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

        return [
            'kind' => 'attendance_auto_clock_out',
            'attendance_id' => $this->attendance->id,
            'employee_id' => $employee?->id,
            'employee_name_ar' => $employee?->name_ar,
            'employee_name_en' => $employee?->name_en,
            'date' => $this->attendance->date->toDateString(),
            'shift_type' => $this->attendance->shift_type,
            'clock_out' => $this->attendance->clock_out?->timezone($tz)->format('Y-m-d H:i'),
            'url' => $this->urlFor($notifiable),
        ];
    }

    private function urlFor(object $notifiable): string
    {
        $date = $this->attendance->date->toDateString();

        if ($notifiable instanceof User && $notifiable->isEmployee()) {
            return route('employee.report', ['date' => $date]);
        }

        if ($notifiable instanceof User && $notifiable->canManageOperations()) {
            return route('admin.dashboard');
        }

        return route('dashboard');
    }
}
