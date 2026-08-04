<?php

namespace App\Notifications;

use App\Models\User;
use App\Models\Vacation;
use Illuminate\Notifications\Notification;

/**
 * Vacation approved / rejected. URL depends on recipient role.
 */
class VacationDecisionNotification extends Notification
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
        $status = $this->vacation->status;
        $kind = $status === Vacation::STATUS_FORCED
            ? 'vacation_forced'
            : 'vacation_decision';

        return [
            'kind' => $kind,
            'vacation_id' => $this->vacation->id,
            'status' => $status,
            'type' => $this->vacation->type,
            'start_date' => $this->vacation->start_date->toDateString(),
            'end_date' => $this->vacation->end_date->toDateString(),
            'days_requested' => (string) $this->vacation->days_requested,
            'url' => $this->urlFor($notifiable),
        ];
    }

    private function urlFor(object $notifiable): string
    {
        if ($notifiable instanceof User
            && (int) $notifiable->id === (int) $this->vacation->user_id
        ) {
            return route('employee.requests', ['tab' => 'vacations']);
        }

        $filter = $this->vacation->status === Vacation::STATUS_PENDING
            ? 'pending'
            : 'decided';

        return route('team-leader.approvals', ['filter' => $filter]);
    }
}
