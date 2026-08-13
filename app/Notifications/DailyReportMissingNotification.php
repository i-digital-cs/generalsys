<?php

namespace App\Notifications;

use App\Models\Attendance;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Notifications\Notification;

/** TL + Managers: a person did not submit their daily report for a work day. */
class DailyReportMissingNotification extends Notification
{
    public function __construct(
        public ?Attendance $attendance,
        public User $person,
        public CarbonInterface $date,
        public string $kind,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => $this->kind,
            'attendance_id' => $this->attendance?->id,
            'employee_id' => $this->person->id,
            'employee_name_ar' => $this->person->name_ar,
            'employee_name_en' => $this->person->name_en,
            'date' => $this->date->toDateString(),
            'url' => route('team-leader.reports'),
        ];
    }
}
