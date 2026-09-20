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
            'report_missing_today' => __('notifications.kinds.report_missing_today'),
            'report_missing_overdue' => __('notifications.kinds.report_missing_overdue'),
            'attendance_auto_clock_out' => __('notifications.kinds.attendance_auto_clock_out'),
            'attendance_geofence_warning' => __('notifications.kinds.attendance_geofence_warning'),
            'attendance_outside_shift_window' => __('notifications.kinds.attendance_outside_shift_window'),
            'attendance_gap_pending' => __('notifications.kinds.attendance_gap_pending'),
            'attendance_gap_tl_decided' => __('notifications.kinds.attendance_gap_tl_decided'),
            'attendance_gap_admin_decided' => __('notifications.kinds.attendance_gap_admin_decided'),
            'attendance_overtime_tl_decided' => __('notifications.kinds.attendance_overtime_tl_decided'),
            'attendance_overtime_admin_decided' => __('notifications.kinds.attendance_overtime_admin_decided'),
            'attendance_force_clock_out' => __('notifications.kinds.attendance_force_clock_out'),
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
                'date' => self::permissionDateLabel($data),
            ]),
            'permission_decision' => __('notifications.bodies.permission_decision', [
                'type' => __('permissions.types.'.($data['type'] ?? 'LEAVE_PERMISSION')),
                'status' => __('permissions.statuses.'.($data['status'] ?? 'PENDING')),
                'date' => self::permissionDateLabel($data),
            ]),
            'permission_forced' => __('notifications.bodies.permission_forced', [
                'type' => __('permissions.types.'.($data['type'] ?? 'LEAVE_PERMISSION')),
                'date' => self::permissionDateLabel($data),
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
            'report_missing_today' => __('notifications.bodies.report_missing_today', [
                'name' => $name,
                'date' => $data['date'] ?? '',
            ]),
            'report_missing_overdue' => __('notifications.bodies.report_missing_overdue', [
                'name' => $name,
                'date' => $data['date'] ?? '',
            ]),
            'attendance_auto_clock_out' => __('notifications.bodies.attendance_auto_clock_out', [
                'name' => $name !== '' ? $name : __('notifications.you'),
                'date' => $data['date'] ?? '',
                'shift' => __('clock.shifts.'.($data['shift_type'] ?? 'FULL_TIME')),
                'time' => $data['clock_out'] ?? '',
            ]),
            'attendance_geofence_warning' => __('notifications.bodies.attendance_geofence_warning', [
                'name' => $name !== '' ? $name : __('notifications.you'),
                'punch' => ($data['punch'] ?? '') === 'CLOCK_OUT'
                    ? __('clock.clock_out')
                    : __('clock.clock_in'),
                'date' => $data['date'] ?? '',
                'distance' => $data['distance_m'] ?? '',
                'radius' => $data['radius_m'] ?? '',
            ]),
            'attendance_outside_shift_window' => __('notifications.bodies.attendance_outside_shift_window', [
                'name' => $name !== '' ? $name : __('notifications.you'),
                'punch' => ($data['punch'] ?? '') === 'CLOCK_OUT'
                    ? __('clock.clock_out')
                    : __('clock.clock_in'),
                'date' => $data['date'] ?? '',
                'shift' => __('clock.shifts.'.($data['shift_type'] ?? 'FULL_TIME')),
                'time' => $data['time'] ?? '',
                'reason' => __('notifications.outside_reasons.'.($data['reason'] ?? 'after_window')),
            ]),
            'attendance_gap_pending' => __('notifications.bodies.attendance_gap_pending', [
                'name' => $name !== '' ? $name : ($data['employee'] ?? ''),
                'minutes' => $data['minutes'] ?? 0,
                'date' => $data['date'] ?? '',
                'shift' => __('clock.shifts.'.($data['shift_type'] ?? 'FULL_TIME')),
            ]),
            'attendance_gap_tl_decided' => __('notifications.bodies.attendance_gap_tl_decided', [
                'name' => $name !== '' ? $name : ($data['employee'] ?? ''),
                'minutes' => $data['minutes'] ?? 0,
                'date' => $data['date'] ?? '',
                'status' => __('attendance_gaps.status.'.($data['tl_recommendation'] ?? 'PENDING')),
            ]),
            'attendance_gap_admin_decided' => __('notifications.bodies.attendance_gap_admin_decided', [
                'minutes' => $data['minutes'] ?? 0,
                'date' => $data['date'] ?? '',
                'status' => __('attendance_gaps.status.'.($data['admin_decision'] ?? 'PENDING')),
            ]),
            'attendance_overtime_tl_decided' => __('notifications.bodies.attendance_overtime_tl_decided', [
                'name' => $name !== '' ? $name : ($data['employee'] ?? ''),
                'minutes' => $data['minutes'] ?? 0,
                'date' => $data['date'] ?? '',
                'status' => __('attendance_review.breakdown.ot_status.'.($data['tl_recommendation'] ?? 'NONE')),
            ]),
            'attendance_overtime_admin_decided' => __('notifications.bodies.attendance_overtime_admin_decided', [
                'minutes' => $data['minutes'] ?? 0,
                'date' => $data['date'] ?? '',
                'status' => __('attendance_review.breakdown.ot_admin.'.($data['admin_decision'] ?? 'PENDING')),
            ]),
            'attendance_force_clock_out' => __('notifications.bodies.attendance_force_clock_out', [
                'actor' => $locale === 'ar'
                    ? ($data['actor_name_ar'] ?? $data['actor_name_en'] ?? '')
                    : ($data['actor_name_en'] ?? $data['actor_name_ar'] ?? ''),
                'name' => $name !== '' ? $name : __('notifications.you'),
                'date' => $data['date'] ?? '',
                'shift' => __('clock.shifts.'.($data['shift_type'] ?? 'FULL_TIME')),
                'time' => $data['clock_out'] ?? '',
            ]),
            default => '',
        };
    }

    /**
     * Single day or From–To period for permission notification bodies.
     *
     * @param  array<string, mixed>  $data
     */
    private static function permissionDateLabel(array $data): string
    {
        $start = (string) ($data['request_date'] ?? '');
        $end = (string) ($data['end_date'] ?? $start);
        if ($start !== '' && $end !== '' && $end !== $start) {
            return $start.' – '.$end;
        }

        return $start !== '' ? $start : $end;
    }
}
