<?php

namespace Tests\Feature\Employee;

use App\Models\Attendance;
use App\Models\User;
use App\Notifications\AttendanceGeofenceWarningNotification;
use App\Services\Admin\SettingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * T-7.2 — required punch GPS + soft office geofence.
 */
class ClockLocationTest extends TestCase
{
    use RefreshDatabase;

    private User $employee;

    private User $leader;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->manager()->create();
        $this->leader = User::factory()->teamLeader()->create();
        $this->employee = User::factory()->employee()->create([
            'team_leader_id' => $this->leader->id,
            'shift_type_default' => Attendance::SHIFT_FULL_TIME,
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
        ]);

        $this->freezeClockToMorningShift();
    }

    public function test_clock_in_requires_gps(): void
    {
        $this->actingAs($this->employee)
            ->from(route('employee.clock'))
            ->post(route('employee.clock.in'), [
                'shift_type' => Attendance::SHIFT_FULL_TIME,
            ])
            ->assertRedirect(route('employee.clock'))
            ->assertSessionHasErrors(['latitude', 'longitude']);
    }

    public function test_clock_in_stores_location_and_warns_when_outside(): void
    {
        Notification::fake();

        $settings = app(SettingService::class);
        $settings->set(SettingService::KEY_OFFICE_LATITUDE, '30.0444000');
        $settings->set(SettingService::KEY_OFFICE_LONGITUDE, '31.2357000');
        $settings->set(SettingService::KEY_OFFICE_RADIUS_METERS, '1000');

        $this->actingAs($this->employee)
            ->post(route('employee.clock.in'), $this->withClockGps([
                'shift_type' => Attendance::SHIFT_FULL_TIME,
                'latitude' => 30.0890,
                'longitude' => 31.2357,
            ]))
            ->assertRedirect(route('employee.clock'))
            ->assertSessionHas('warning');

        $row = Attendance::where('user_id', $this->employee->id)->firstOrFail();
        $this->assertTrue((bool) $row->clock_in_outside_geofence);
        $this->assertNotNull($row->clock_in_lat);
        $this->assertNotNull($row->clock_in_distance_m);
        $this->assertGreaterThan(1000, (int) $row->clock_in_distance_m);

        Notification::assertSentTo($this->employee, AttendanceGeofenceWarningNotification::class);
        Notification::assertSentTo($this->leader, AttendanceGeofenceWarningNotification::class);
        Notification::assertSentTo($this->manager, AttendanceGeofenceWarningNotification::class);
    }

    public function test_clock_screen_shows_location_ui_and_maps_link_after_punch(): void
    {
        $this->actingAs($this->employee)
            ->get(route('employee.clock'))
            ->assertOk()
            ->assertSee(__('clock.location.required_hint_full_time'), false)
            ->assertSee(__('clock.location.check_btn'), false)
            ->assertSee(__('clock.location.enable_btn'), false)
            ->assertSee('name="latitude"', false);

        $this->actingAs($this->employee)
            ->post(route('employee.clock.in'), $this->withClockGps([
                'shift_type' => Attendance::SHIFT_FULL_TIME,
            ]))
            ->assertRedirect(route('employee.clock'));

        $row = Attendance::where('user_id', $this->employee->id)->firstOrFail();

        $this->actingAs($this->employee)
            ->get(route('employee.clock'))
            ->assertOk()
            ->assertSee(__('clock.location.in_label'), false)
            ->assertSee('google.com/maps?q=', false);

        $this->actingAs($this->leader)
            ->get(route('team-leader.dashboard'))
            ->assertOk()
            ->assertSee('google.com/maps?q=', false);

        $this->assertSame(30.0444, round((float) $row->clock_in_lat, 4));
    }

    public function test_manager_saves_office_settings(): void
    {
        $this->actingAs($this->manager)
            ->put(route('admin.settings.update'), [
                'default_language' => 'ar',
                'default_currency' => 'EGP',
                'office_latitude' => '30.0500000',
                'office_longitude' => '31.2400000',
                'office_radius_meters' => 1500,
            ])
            ->assertRedirect(route('admin.settings'));

        $this->assertDatabaseHas('settings', ['key' => 'office_latitude', 'value' => '30.0500000']);
        $this->assertDatabaseHas('settings', ['key' => 'office_longitude', 'value' => '31.2400000']);
        $this->assertDatabaseHas('settings', ['key' => 'office_radius_meters', 'value' => '1500']);
    }

    public function test_online_clock_in_does_not_require_gps(): void
    {
        $this->employee->update([
            'shift_type_default' => Attendance::SHIFT_ONLINE_HALF,
            'shift_types_default' => [
                ['type' => Attendance::SHIFT_ONLINE_HALF, 'start' => '06:00', 'end' => '10:00'],
            ],
        ]);
        Carbon::setTestNow(Carbon::parse('2026-08-03 07:00:00', config('app.timezone')));

        $this->actingAs($this->employee)
            ->post(route('employee.clock.in'), [
                'shift_type' => Attendance::SHIFT_ONLINE_HALF,
            ])
            ->assertRedirect(route('employee.clock'))
            ->assertSessionHas('success')
            ->assertSessionDoesntHaveErrors(['latitude', 'longitude']);

        $this->assertDatabaseHas('attendance', [
            'user_id' => $this->employee->id,
            'shift_type' => Attendance::SHIFT_ONLINE_HALF,
        ]);
        $this->assertNull(Attendance::where('user_id', $this->employee->id)->value('clock_in_lat'));

        Carbon::setTestNow();
    }
}
