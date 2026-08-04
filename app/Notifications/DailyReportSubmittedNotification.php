<?php

namespace App\Notifications;

use App\Models\Attendance;
use Illuminate\Notifications\Notification;

/** TL: employee submitted a daily report. */
class DailyReportSubmittedNotification extends Notification
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

        return [
            'kind' => 'report_submitted',
            'attendance_id' => $this->attendance->id,
            'employee_id' => $employee?->id,
            'employee_name_ar' => $employee?->name_ar,
            'employee_name_en' => $employee?->name_en,
            'date' => $this->attendance->date->toDateString(),
            'url' => route('team-leader.reports.show', $this->attendance),
        ];
    }
}
