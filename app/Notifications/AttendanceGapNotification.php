<?php

namespace App\Notifications;

use App\Models\AttendanceGap;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class AttendanceGapNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly AttendanceGap $gap,
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
        $this->gap->loadMissing('attendance.user');
        $employee = $this->gap->attendance?->user;

        return [
            'kind' => $this->kind,
            'gap_id' => $this->gap->id,
            'attendance_id' => $this->gap->attendance_id,
            'minutes' => $this->gap->minutes,
            'employee' => $employee?->localizedName(),
            'employee_name_en' => $employee?->name_en,
            'employee_name_ar' => $employee?->name_ar,
            'date' => $this->gap->attendance?->date?->toDateString(),
            'shift_type' => $this->gap->attendance?->shift_type,
            'tl_recommendation' => $this->gap->tl_recommendation,
            'admin_decision' => $this->gap->admin_decision,
            'url' => $this->urlFor($notifiable),
        ];
    }

    private function urlFor(object $notifiable): string
    {
        if ($notifiable instanceof User && in_array($notifiable->role, User::fieldStaffRoles(), true)) {
            return route('employee.clock');
        }

        $filter = $this->kind === 'attendance_gap_admin_decided' ? 'decided' : 'pending';

        return route('team-leader.approvals', ['filter' => $filter]);
    }
}
