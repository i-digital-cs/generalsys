<?php

namespace App\Notifications;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Notifications\Notification;

/**
 * Permission accepted / rejected / forced. No money.
 * URL depends on recipient role (employee vs TL/Admin).
 */
class PermissionDecisionNotification extends Notification
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
        $status = $this->permission->status;
        $kind = $status === Permission::STATUS_FORCED
            ? 'permission_forced'
            : 'permission_decision';

        return [
            'kind' => $kind,
            'permission_id' => $this->permission->id,
            'status' => $status,
            'type' => $this->permission->type,
            'request_date' => $this->permission->request_date->toDateString(),
            'end_date' => ($this->permission->end_date ?? $this->permission->request_date)->toDateString(),
            'url' => $this->urlFor($notifiable),
        ];
    }

    private function urlFor(object $notifiable): string
    {
        if ($notifiable instanceof User && $notifiable->isEmployee()) {
            return route('employee.requests', ['tab' => 'permissions']);
        }

        $filter = $this->permission->status === Permission::STATUS_PENDING
            ? 'pending'
            : 'all';

        return route('team-leader.approvals', ['filter' => $filter]);
    }
}
