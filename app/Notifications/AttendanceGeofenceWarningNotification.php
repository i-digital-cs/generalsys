<?php

namespace App\Notifications;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Notifications\Notification;

/** Soft geofence: punch accepted but outside office radius. */
class AttendanceGeofenceWarningNotification extends Notification
{
    public function __construct(
        public Attendance $attendance,
        public string $kindPunch, // CLOCK_IN | CLOCK_OUT
        public int $distanceM,
        public int $radiusM,
    ) {}

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
        $isIn = $this->kindPunch === 'CLOCK_IN';

        $lat = $isIn ? $this->attendance->clock_in_lat : $this->attendance->clock_out_lat;
        $lng = $isIn ? $this->attendance->clock_in_lng : $this->attendance->clock_out_lng;

        return [
            'kind' => 'attendance_geofence_warning',
            'attendance_id' => $this->attendance->id,
            'punch' => $this->kindPunch,
            'employee_id' => $employee?->id,
            'employee_name_ar' => $employee?->name_ar,
            'employee_name_en' => $employee?->name_en,
            'date' => $this->attendance->date->toDateString(),
            'distance_m' => $this->distanceM,
            'radius_m' => $this->radiusM,
            'lat' => $lat !== null ? (float) $lat : null,
            'lng' => $lng !== null ? (float) $lng : null,
            'maps_url' => ($lat !== null && $lng !== null)
                ? sprintf('https://www.google.com/maps?q=%s,%s', $lat, $lng)
                : null,
            'url' => $this->urlFor($notifiable),
        ];
    }

    private function urlFor(object $notifiable): string
    {
        if ($notifiable instanceof User && (
            $notifiable->isEmployee() || $notifiable->isAccountant()
        )) {
            return route('employee.clock');
        }

        if ($notifiable instanceof User && $notifiable->isManager()) {
            return route('admin.dashboard');
        }

        return route('dashboard');
    }
}
