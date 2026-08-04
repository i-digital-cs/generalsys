<?php

namespace App\Notifications;

use App\Models\Attendance;
use Illuminate\Notifications\Notification;

/** Employee: TL replied to their daily report. */
class DailyReportRepliedNotification extends Notification
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
        return [
            'kind' => 'report_replied',
            'attendance_id' => $this->attendance->id,
            'date' => $this->attendance->date->toDateString(),
            'url' => route('employee.report', ['date' => $this->attendance->date->toDateString()]),
        ];
    }
}
