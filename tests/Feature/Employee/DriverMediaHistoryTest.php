<?php

namespace Tests\Feature\Employee;

use App\Models\Attendance;
use App\Models\AttendancePhoto;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Driver media history — own photos with filters.
 */
class DriverMediaHistoryTest extends TestCase
{
    use RefreshDatabase;

    private User $driver;

    private User $employee;

    private User $otherDriver;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        app()->setLocale('en');

        $leader = User::factory()->teamLeader()->create();
        $this->driver = User::factory()->driver()->create([
            'team_leader_id' => $leader->id,
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
        ]);
        $this->otherDriver = User::factory()->driver()->create([
            'team_leader_id' => $leader->id,
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
        ]);
        $this->employee = User::factory()->employee()->create([
            'team_leader_id' => $leader->id,
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
        ]);
    }

    private function seedPhoto(User $user, string $kind, string $date, string $title, array $gps = []): AttendancePhoto
    {
        $attendance = Attendance::query()
            ->where('user_id', $user->id)
            ->whereDate('date', $date)
            ->where('shift_type', Attendance::SHIFT_FULL_TIME)
            ->first();

        if ($attendance === null) {
            $attendance = Attendance::query()->create([
                'user_id' => $user->id,
                'date' => $date,
                'shift_type' => Attendance::SHIFT_FULL_TIME,
                'clock_in' => $date.' 10:00:00',
                'clock_out' => $date.' 18:00:00',
                'planned_hours' => 8,
                'actual_hours' => 8,
                'status' => Attendance::STATUS_PRESENT,
            ]);
        }

        $path = UploadedFile::fake()->image('shot.jpg', 120, 120)
            ->store("attendance-photos/{$user->id}/{$attendance->id}", 'local');

        $order = (int) $attendance->photos()->where('kind', $kind)->max('sort_order');

        return AttendancePhoto::query()->create(array_merge([
            'attendance_id' => $attendance->id,
            'kind' => $kind,
            'file_path' => $path,
            'original_name' => 'shot.jpg',
            'description' => $title,
            'mime_type' => 'image/jpeg',
            'size_bytes' => 120,
            'sort_order' => $order + 1,
            'uploaded_at' => Carbon::parse($date.' 12:00:00'),
            'lat' => null,
            'lng' => null,
            'outside_geofence' => false,
        ], $gps));
    }

    public function test_driver_can_open_media_history_and_sees_own_photos(): void
    {
        $mine = $this->seedPhoto($this->driver, AttendancePhoto::KIND_FIELD, '2026-08-10', 'Client gate', [
            'lat' => 30.0444,
            'lng' => 31.2357,
            'distance_m' => 40,
            'outside_geofence' => false,
        ]);
        $this->seedPhoto($this->otherDriver, AttendancePhoto::KIND_FIELD, '2026-08-10', 'Other secret');

        $this->actingAs($this->driver)
            ->get(route('employee.media', [
                'from' => '2026-08-01',
                'to' => '2026-08-31',
            ]))
            ->assertOk()
            ->assertSee(__('media.history.title'), false)
            ->assertSee('Client gate', false)
            ->assertSee(__('media.history.kinds.FIELD'), false)
            ->assertSee(route('attendance.photos.show', $mine), false)
            ->assertDontSee('Other secret', false);
    }

    public function test_filters_by_kind_location_and_search(): void
    {
        $this->seedPhoto($this->driver, AttendancePhoto::KIND_FIELD, '2026-08-10', 'Field stop A', [
            'lat' => 29.0,
            'lng' => 31.0,
            'outside_geofence' => true,
            'distance_m' => 5000,
        ]);
        $this->seedPhoto($this->driver, AttendancePhoto::KIND_CLOCK_IN, '2026-08-10', 'Morning punch', [
            'lat' => 30.0444,
            'lng' => 31.2357,
            'outside_geofence' => false,
            'distance_m' => 20,
        ]);
        $this->seedPhoto($this->driver, AttendancePhoto::KIND_CLOCK_OUT, '2026-08-11', 'Evening punch');

        $this->actingAs($this->driver)
            ->get(route('employee.media', [
                'from' => '2026-08-01',
                'to' => '2026-08-31',
                'kind' => 'FIELD',
            ]))
            ->assertOk()
            ->assertSee('Field stop A', false)
            ->assertDontSee('Morning punch', false)
            ->assertDontSee('Evening punch', false);

        $this->actingAs($this->driver)
            ->get(route('employee.media', [
                'from' => '2026-08-01',
                'to' => '2026-08-31',
                'location' => 'outside',
            ]))
            ->assertOk()
            ->assertSee('Field stop A', false)
            ->assertDontSee('Morning punch', false);

        $this->actingAs($this->driver)
            ->get(route('employee.media', [
                'from' => '2026-08-01',
                'to' => '2026-08-31',
                'q' => 'Evening',
            ]))
            ->assertOk()
            ->assertSee('Evening punch', false)
            ->assertDontSee('Field stop A', false);
    }

    public function test_employee_and_manager_are_blocked(): void
    {
        $this->actingAs($this->employee)
            ->get(route('employee.media'))
            ->assertForbidden();

        $manager = User::factory()->manager()->create();
        $this->actingAs($manager)
            ->get(route('employee.media'))
            ->assertForbidden();
    }

    public function test_range_longer_than_92_days_is_rejected(): void
    {
        $this->actingAs($this->driver)
            ->from(route('employee.media'))
            ->get(route('employee.media', [
                'from' => '2026-01-01',
                'to' => '2026-05-01',
            ]))
            ->assertRedirect()
            ->assertSessionHasErrors('to');
    }
}
