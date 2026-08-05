<?php

namespace App\Support;

/**
 * Localized labels for database notification payloads.
 */
class NotificationMessage
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function title(array $data): string
    {
        return match ($data['kind'] ?? '') {
            'permission_submitted' => __('notifications.kinds.permission_submitted'),
            'permission_decision' => __('notifications.kinds.permission_decision', [
                'status' => __('permissions.statuses.'.($data['status'] ?? 'PENDING')),
            ]),
            'permission_forced' => __('notifications.kinds.permission_forced'),
            'vacation_submitted' => __('notifications.kinds.vacation_submitted'),
            'vacation_decision' => __('notifications.kinds.vacation_decision', [
                'status' => __('vacations.statuses.'.($data['status'] ?? 'PENDING')),
            ]),
            'vacation_forced' => __('notifications.kinds.vacation_forced'),
            'report_submitted' => __('notifications.kinds.report_submitted'),
            'report_replied' => __('notifications.kinds.report_replied'),
            'attendance_auto_clock_out' => __('notifications.kinds.attendance_auto_clock_out'),
            default => __('notifications.kinds.generic'),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function body(array $data): string
    {
        $locale = app()->getLocale();
        $name = $locale === 'ar'
            ? ($data['employee_name_ar'] ?? $data['employee_name_en'] ?? '')
            : ($data['employee_name_en'] ?? $data['employee_name_ar'] ?? '');

        return match ($data['kind'] ?? '') {
            'permission_submitted' => __('notifications.bodies.permission_submitted', [
                'name' => $name,
                'type' => __('permissions.types.'.($data['type'] ?? 'LEAVE_PERMISSION')),
                'date' => $data['request_date'] ?? '',
            ]),
            'permission_decision' => __('notifications.bodies.permission_decision', [
                'type' => __('permissions.types.'.($data['type'] ?? 'LEAVE_PERMISSION')),
                'status' => __('permissions.statuses.'.($data['status'] ?? 'PENDING')),
                'date' => $data['request_date'] ?? '',
            ]),
            'permission_forced' => __('notifications.bodies.permission_forced', [
                'type' => __('permissions.types.'.($data['type'] ?? 'LEAVE_PERMISSION')),
                'date' => $data['request_date'] ?? '',
            ]),
            'vacation_submitted' => __('notifications.bodies.vacation_submitted', [
                'name' => $name,
                'type' => __('vacations.types.'.($data['type'] ?? 'ANNUAL')),
                'days' => $data['days_requested'] ?? '',
                'start' => $data['start_date'] ?? '',
                'end' => $data['end_date'] ?? '',
            ]),
            'vacation_decision' => __('notifications.bodies.vacation_decision', [
                'type' => __('vacations.types.'.($data['type'] ?? 'ANNUAL')),
                'status' => __('vacations.statuses.'.($data['status'] ?? 'PENDING')),
                'days' => $data['days_requested'] ?? '',
                'start' => $data['start_date'] ?? '',
                'end' => $data['end_date'] ?? '',
            ]),
            'vacation_forced' => __('notifications.bodies.vacation_forced', [
                'type' => __('vacations.types.'.($data['type'] ?? 'ANNUAL')),
                'start' => $data['start_date'] ?? '',
                'end' => $data['end_date'] ?? '',
            ]),
            'report_submitted' => __('notifications.bodies.report_submitted', [
                'name' => $name,
                'date' => $data['date'] ?? '',
            ]),
            'report_replied' => __('notifications.bodies.report_replied', [
                'date' => $data['date'] ?? '',
            ]),
            'attendance_auto_clock_out' => __('notifications.bodies.attendance_auto_clock_out', [
                'name' => $name !== '' ? $name : __('notifications.you'),
                'date' => $data['date'] ?? '',
                'shift' => __('clock.shifts.'.($data['shift_type'] ?? 'FULL_TIME')),
                'time' => $data['clock_out'] ?? '',
            ]),
            default => '',
        };
    }
}
