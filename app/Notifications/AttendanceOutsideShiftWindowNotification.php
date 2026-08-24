<?php

namespace App\Notifications;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Notifications\Notification;

/** Punch accepted outside the planned shift window (early or after end). */
class AttendanceOutsideShiftWindowNotification extends Notification
{
    public function __construct(
        public Attendance $attendance,
        public string $kindPunch, // CLOCK_IN | CLOCK_OUT
        public string $reason, // before_window | after_window
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
        $isIn = $this->kindPunch === 'CLOCK_IN';
        $at = $isIn ? $this->attendance->clock_in : $this->attendance->clock_out;

        return [
            'kind' => 'attendance_outside_shift_window',
            'attendance_id' => $this->attendance->id,
            'punch' => $this->kindPunch,
            'reason' => $this->reason,
            'employee_id' => $employee?->id,
            'employee_name_ar' => $employee?->name_ar,
            'employee_name_en' => $employee?->name_en,
            'date' => $this->attendance->date->toDateString(),
            'shift_type' => $this->attendance->shift_type,
            'time' => $at?->timezone(config('app.timezone'))->format('H:i'),
            'url' => $this->urlFor($notifiable),
        ];
    }

    private function urlFor(object $notifiable): string
    {
        $employee = $this->attendance->user;

        if ($notifiable instanceof User && $employee !== null && (int) $notifiable->id === (int) $employee->id) {
            return route('employee.clock');
        }

        if ($notifiable instanceof User && $notifiable->canManageOperations()) {
            return route('admin.dashboard');
        }

        return route('dashboard');
    }
}
