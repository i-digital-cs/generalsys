<?php

namespace App\Services\Attendance;

use App\Services\Admin\SettingService;

/**
 * Soft office geofence — Haversine distance vs Admin Settings office point.
 */
class GeofenceService
{
    public function __construct(private readonly SettingService $settings) {}

    /**
     * @return array{lat: float, lng: float, radius_m: int}|null
     */
    public function office(): ?array
    {
        $lat = $this->settings->get(SettingService::KEY_OFFICE_LATITUDE);
        $lng = $this->settings->get(SettingService::KEY_OFFICE_LONGITUDE);

        if ($lat === null || $lat === '' || $lng === null || $lng === '') {
            return null;
        }

        $radius = (int) ($this->settings->get(
            SettingService::KEY_OFFICE_RADIUS_METERS,
            (string) config('attendance.location.radius_meters', 1000),
        ) ?? 1000);

        return [
            'lat' => (float) $lat,
            'lng' => (float) $lng,
            'radius_m' => max(1, $radius),
        ];
    }

    /**
     * Distance in meters (Haversine). Null if office not configured.
     */
    public function distanceMeters(float $lat, float $lng): ?int
    {
        $office = $this->office();
        if ($office === null) {
            return null;
        }

        $earth = 6371000.0;
        $latFrom = deg2rad($office['lat']);
        $latTo = deg2rad($lat);
        $deltaLat = deg2rad($lat - $office['lat']);
        $deltaLng = deg2rad($lng - $office['lng']);

        $a = sin($deltaLat / 2) ** 2
            + cos($latFrom) * cos($latTo) * sin($deltaLng / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return (int) round($earth * $c);
    }

    /**
     * @return array{distance_m: ?int, outside: bool}
     */
    public function evaluate(float $lat, float $lng): array
    {
        $distance = $this->distanceMeters($lat, $lng);
        $office = $this->office();

        if ($distance === null || $office === null) {
            return ['distance_m' => null, 'outside' => false];
        }

        return [
            'distance_m' => $distance,
            'outside' => $distance > $office['radius_m'],
        ];
    }

    public static function googleMapsUrl(float $lat, float $lng): string
    {
        return sprintf(
            'https://www.google.com/maps?q=%s,%s',
            number_format($lat, 7, '.', ''),
            number_format($lng, 7, '.', ''),
        );
    }
}
