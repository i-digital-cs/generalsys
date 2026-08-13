<?php

namespace Tests\Unit\Attendance;

use App\Services\Admin\SettingService;
use App\Services\Attendance\GeofenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeofenceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_distance_null_when_office_not_configured(): void
    {
        $service = app(GeofenceService::class);

        $this->assertNull($service->office());
        $this->assertNull($service->distanceMeters(30.0444, 31.2357));
        $this->assertSame(
            ['distance_m' => null, 'outside' => false],
            $service->evaluate(30.0444, 31.2357),
        );
    }

    public function test_haversine_marks_outside_beyond_radius(): void
    {
        $settings = app(SettingService::class);
        $settings->set(SettingService::KEY_OFFICE_LATITUDE, '30.0444000');
        $settings->set(SettingService::KEY_OFFICE_LONGITUDE, '31.2357000');
        $settings->set(SettingService::KEY_OFFICE_RADIUS_METERS, '1000');

        $service = app(GeofenceService::class);

        $near = $service->evaluate(30.0444000, 31.2357000);
        $this->assertFalse($near['outside']);
        $this->assertSame(0, $near['distance_m']);

        // ~5 km north of office
        $far = $service->evaluate(30.0890000, 31.2357000);
        $this->assertTrue($far['outside']);
        $this->assertGreaterThan(1000, (int) $far['distance_m']);
    }

    public function test_google_maps_url(): void
    {
        $this->assertSame(
            'https://www.google.com/maps?q=30.0444000,31.2357000',
            GeofenceService::googleMapsUrl(30.0444, 31.2357),
        );
    }
}
