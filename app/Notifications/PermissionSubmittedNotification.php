<?php

namespace App\Notifications;

use App\Models\Permission;
use Illuminate\Notifications\Notification;

/** TL: employee submitted a leave/late permission. No money in payload. */
class PermissionSubmittedNotification extends Notification
{
    public function __construct(public Permission $permission) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $this->permission->loadMissing('user');
        $employee = $this->permission->user;

        return [
            'kind' => 'permission_submitted',
            'permission_id' => $this->permission->id,
            'employee_id' => $employee?->id,
            'employee_name_ar' => $employee?->name_ar,
            'employee_name_en' => $employee?->name_en,
            'type' => $this->permission->type,
            'request_date' => $this->permission->request_date->toDateString(),
            'url' => route('team-leader.approvals'),
        ];
    }
}
