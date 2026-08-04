<?php

namespace App\Notifications;

use App\Models\Vacation;
use Illuminate\Notifications\Notification;

/** TL/Admin: someone submitted a vacation request. */
class VacationSubmittedNotification extends Notification
{
    public function __construct(public Vacation $vacation) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $this->vacation->loadMissing('user');
        $employee = $this->vacation->user;

        return [
            'kind' => 'vacation_submitted',
            'vacation_id' => $this->vacation->id,
            'employee_id' => $employee?->id,
            'employee_name_ar' => $employee?->name_ar,
            'employee_name_en' => $employee?->name_en,
            'type' => $this->vacation->type,
            'start_date' => $this->vacation->start_date->toDateString(),
            'end_date' => $this->vacation->end_date->toDateString(),
            'days_requested' => (string) $this->vacation->days_requested,
            'url' => route('team-leader.approvals'),
        ];
    }
}
