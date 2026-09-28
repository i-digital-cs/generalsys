<?php

namespace App\Notifications;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class AttendanceOvertimeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Attendance $attendance,
        private readonly string $kind,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $this->attendance->loadMissing('user');
        $employee = $this->attendance->user;

        return [
            'kind' => $this->kind,
            'attendance_id' => $this->attendance->id,
            'minutes' => (int) $this->attendance->recommended_overtime_minutes,
            'delta_minutes' => max(0, $this->attendance->overtimeDeltaMinutes()),
            'employee' => $employee?->localizedName(),
            'employee_name_en' => $employee?->name_en,
            'employee_name_ar' => $employee?->name_ar,
            'date' => $this->attendance->date?->toDateString(),
            'shift_type' => $this->attendance->shift_type,
            'tl_recommendation' => $this->attendance->overtime_tl_recommendation,
            'admin_decision' => $this->attendance->overtime_admin_decision,
            'url' => $this->urlFor($notifiable),
        ];
    }

    private function urlFor(object $notifiable): string
    {
        if ($notifiable instanceof User && in_array($notifiable->role, User::fieldStaffRoles(), true)) {
            return route('employee.clock');
        }

        $filter = $this->kind === 'attendance_overtime_admin_decided' ? 'decided' : 'pending';

        return route('team-leader.approvals', ['filter' => $filter]);
    }
}
