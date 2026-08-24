<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendancePhoto;
use App\Models\PayrollRecord;
use App\Models\RestDay;
use App\Models\User;
use App\Services\Attendance\AttendanceService;
use App\Services\Attendance\ShiftAssignmentService;
use App\Services\Auth\PostLoginRedirector;
use App\Services\Employees\RestDayService;
use App\Services\Employees\TeamMembershipService;
use App\Services\Payroll\PayrollGenerator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * T-7.8 — DRIVER: clock + daily report; TL membership; required punch photos + GPS;
 * included in Admin payroll like employees.
 */
class DriverRoleTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $assistant;

    private User $leader;

    private User $driver;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->manager = User::factory()->manager()->create();
        $this->assistant = User::factory()->assistant()->create();
        $this->leader = User::factory()->teamLeader()->create();
        $this->driver = User::factory()->driver()->create([
            'team_leader_id' => $this->leader->id,
            'shift_type_default' => Attendance::SHIFT_FULL_TIME,
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
            'base_salary' => 4500,
        ]);

        $this->freezeClockToMorningShift();
    }

    /**
     * @return array<string, mixed>
     */
    private function driverPayload(array $overrides = []): array
    {
        return array_merge([
            'name_ar' => 'سائق جديد',
            'name_en' => 'New Driver',
            'email' => 'new.driver@hrpro.test',
            'password' => 'password123',
            'role' => User::ROLE_DRIVER,
            'currency' => 'EGP',
            'language_preference' => 'ar',
            'team_leader_ids' => [$this->leader->id],
            'default_shifts' => [
                ['shift_type' => 'FULL_TIME', 'start' => '10:00', 'end' => '18:00'],
                ['shift_type' => '', 'start' => '', 'end' => ''],
            ],
            'status' => 'ACTIVE',
            'base_salary' => 4500,
            'profile' => [
                'national_id' => null,
                'birth_date' => null,
                'address' => null,
                'phone' => null,
                'gender' => null,
                'religion' => null,
                'marital_status' => null,
                'employee_code' => null,
            ],
            'contract' => [
                'hire_date' => '2026-01-01',
                'contract_start' => '2026-01-01',
                'contract_end' => '2027-01-01',
                'contract_type' => 'سنة',
                'is_active' => '1',
            ],
        ], $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    private function withRequiredPunchPhoto(array $extra = []): array
    {
        return $this->withClockGps(array_merge([
            'photos' => [UploadedFile::fake()->image('gate.jpg', 200, 200)],
            'photo_descriptions' => ['At the main gate'],
        ], $extra));
    }

    public function test_manager_and_assistant_can_create_driver_with_team_leader(): void
    {
        $this->actingAs($this->manager)
            ->post(route('admin.employees.store'), $this->driverPayload())
            ->assertRedirect();

        $created = User::where('email', 'new.driver@hrpro.test')->firstOrFail();
        $this->assertTrue($created->isDriver());
        $this->assertTrue($created->requiresPunchPhotos());
        $this->assertContains($this->leader->id, app(TeamMembershipService::class)->leaderIds($created));

        $this->actingAs($this->assistant)
            ->post(route('admin.employees.store'), $this->driverPayload([
                'email' => 'assistant.driver@hrpro.test',
                'name_en' => 'Assistant Driver',
            ]))
            ->assertRedirect();

        $this->assertTrue(User::where('email', 'assistant.driver@hrpro.test')->firstOrFail()->isDriver());
    }

    public function test_driver_menu_has_clock_and_report_and_hr_lands_on_clock(): void
    {
        $routes = array_column(config('menu.DRIVER'), 'route');
        $this->assertSame(['employee.clock', 'employee.report', 'employee.media'], $routes);

        $this->assertSame(
            'employee.clock',
            app(PostLoginRedirector::class)->hrRouteNameFor($this->driver),
        );
    }

    public function test_team_leader_sees_driver_on_team(): void
    {
        $visible = app(TeamMembershipService::class)->visibleEmployees($this->leader);
        $this->assertTrue($visible->contains(fn (User $u) => $u->is($this->driver)));
    }

    public function test_driver_included_in_payroll_eligible_ids(): void
    {
        $ids = app(PayrollGenerator::class)->eligibleUserIds();
        $this->assertContains($this->driver->id, $ids);
    }

    public function test_driver_clock_in_requires_photo_and_description(): void
    {
        $this->actingAs($this->driver)
            ->from(route('employee.clock'))
            ->post(route('employee.clock.in'), $this->withClockGps([
                'shift_type' => Attendance::SHIFT_FULL_TIME,
            ]))
            ->assertSessionHasErrors('photos');

        $this->actingAs($this->driver)
            ->from(route('employee.clock'))
            ->post(route('employee.clock.in'), $this->withClockGps([
                'shift_type' => Attendance::SHIFT_FULL_TIME,
                'photos' => [UploadedFile::fake()->image('gate.jpg', 200, 200)],
                'photo_descriptions' => [''],
            ]))
            ->assertSessionHasErrors();

        $this->assertSame(0, Attendance::where('user_id', $this->driver->id)->count());
    }

    public function test_driver_clock_in_and_out_with_required_photos(): void
    {
        $this->actingAs($this->driver)
            ->post(route('employee.clock.in'), $this->withRequiredPunchPhoto([
                'shift_type' => Attendance::SHIFT_FULL_TIME,
            ]))
            ->assertRedirect(route('employee.clock'));

        $attendance = Attendance::where('user_id', $this->driver->id)->firstOrFail();
        $this->assertSame(1, $attendance->photos()->where('kind', AttendancePhoto::KIND_CLOCK_IN)->count());
        $this->assertSame(
            'At the main gate',
            $attendance->photos()->where('kind', AttendancePhoto::KIND_CLOCK_IN)->value('description'),
        );
        $clockInPhoto = $attendance->photos()->where('kind', AttendancePhoto::KIND_CLOCK_IN)->firstOrFail();
        $this->assertNotNull($clockInPhoto->lat);
        $this->assertNotNull($clockInPhoto->lng);

        Carbon::setTestNow(Carbon::parse('2026-08-03 18:00:00', config('app.timezone')));

        $this->actingAs($this->driver)
            ->post(route('employee.clock.out'), $this->withRequiredPunchPhoto([
                'photo_descriptions' => ['Leaving the site'],
            ]))
            ->assertRedirect(route('employee.report', ['date' => '2026-08-03']));

        $attendance->refresh();
        $this->assertNotNull($attendance->clock_out);
        $this->assertSame(1, $attendance->photos()->where('kind', AttendancePhoto::KIND_CLOCK_OUT)->count());
    }

    public function test_driver_can_upload_field_photos_during_open_shift(): void
    {
        $this->actingAs($this->driver)
            ->post(route('employee.clock.in'), $this->withRequiredPunchPhoto([
                'shift_type' => Attendance::SHIFT_FULL_TIME,
            ]))
            ->assertRedirect(route('employee.clock'));

        $this->actingAs($this->driver)
            ->get(route('employee.clock'))
            ->assertOk()
            ->assertSee(__('clock.field_upload.title'), false)
            ->assertSee(__('clock.field_upload.photos_hint', [
                'mb' => (int) round(((int) config('attendance.photos.max_kilobytes', 51200)) / 1024),
            ]), false)
            ->assertSee(__('clock.photos.title_label'), false);

        $this->actingAs($this->driver)
            ->post(route('employee.clock.field-photos'), $this->withRequiredPunchPhoto([
                'photo_descriptions' => ['Delivery at client gate'],
            ]))
            ->assertRedirect(route('employee.clock'))
            ->assertSessionHas('success');

        $attendance = Attendance::where('user_id', $this->driver->id)->firstOrFail();
        $field = $attendance->photos()->where('kind', AttendancePhoto::KIND_FIELD)->firstOrFail();
        $this->assertSame('Delivery at client gate', $field->description);
        $this->assertNotNull($field->lat);
        $this->assertNotNull($field->lng);
        $this->assertNotNull($field->attendance_segment_id);
    }

    public function test_driver_can_upload_multiple_field_photos_with_titles(): void
    {
        $this->actingAs($this->driver)
            ->post(route('employee.clock.in'), $this->withRequiredPunchPhoto([
                'shift_type' => Attendance::SHIFT_FULL_TIME,
            ]))
            ->assertRedirect(route('employee.clock'));

        $this->actingAs($this->driver)
            ->post(route('employee.clock.field-photos'), $this->withClockGps([
                'photos' => [
                    UploadedFile::fake()->image('stop-a.jpg', 200, 200),
                    UploadedFile::fake()->image('stop-b.jpg', 200, 200),
                    UploadedFile::fake()->image('stop-c.jpg', 200, 200),
                ],
                'photo_descriptions' => [
                    'Client gate',
                    'Loading dock',
                    'Signed receipt',
                ],
            ]))
            ->assertRedirect(route('employee.clock'))
            ->assertSessionHas('success');

        $attendance = Attendance::where('user_id', $this->driver->id)->firstOrFail();
        $fields = $attendance->photos()
            ->where('kind', AttendancePhoto::KIND_FIELD)
            ->orderBy('sort_order')
            ->get();

        $this->assertCount(3, $fields);
        $this->assertSame(['Client gate', 'Loading dock', 'Signed receipt'], $fields->pluck('description')->all());
        $this->assertTrue($fields->every(fn (AttendancePhoto $photo) => $photo->lat !== null && $photo->lng !== null));
    }

    public function test_driver_multi_field_upload_rejects_missing_title_on_any_image(): void
    {
        $this->actingAs($this->driver)
            ->post(route('employee.clock.in'), $this->withRequiredPunchPhoto([
                'shift_type' => Attendance::SHIFT_FULL_TIME,
            ]))
            ->assertRedirect();

        $this->actingAs($this->driver)
            ->from(route('employee.clock'))
            ->post(route('employee.clock.field-photos'), $this->withClockGps([
                'photos' => [
                    UploadedFile::fake()->image('a.jpg', 200, 200),
                    UploadedFile::fake()->image('b.jpg', 200, 200),
                ],
                'photo_descriptions' => ['Only first has title', ''],
            ]))
            ->assertSessionHasErrors();

        $this->assertSame(
            0,
            AttendancePhoto::query()
                ->where('kind', AttendancePhoto::KIND_FIELD)
                ->whereHas('attendance', fn ($q) => $q->where('user_id', $this->driver->id))
                ->count(),
        );
    }

    public function test_driver_field_upload_requires_gps_and_description(): void
    {
        $this->actingAs($this->driver)
            ->post(route('employee.clock.in'), $this->withRequiredPunchPhoto([
                'shift_type' => Attendance::SHIFT_FULL_TIME,
            ]))
            ->assertRedirect();

        $this->actingAs($this->driver)
            ->from(route('employee.clock'))
            ->post(route('employee.clock.field-photos'), [
                'photos' => [UploadedFile::fake()->image('spot.jpg', 200, 200)],
                'photo_descriptions' => ['At stop'],
            ])
            ->assertSessionHasErrors(['latitude', 'longitude']);

        $this->actingAs($this->driver)
            ->from(route('employee.clock'))
            ->post(route('employee.clock.field-photos'), $this->withClockGps([
                'photos' => [UploadedFile::fake()->image('spot.jpg', 200, 200)],
                'photo_descriptions' => [''],
            ]))
            ->assertSessionHasErrors();

        $this->assertSame(
            0,
            AttendancePhoto::query()
                ->where('kind', AttendancePhoto::KIND_FIELD)
                ->whereHas('attendance', fn ($q) => $q->where('user_id', $this->driver->id))
                ->count(),
        );
    }

    public function test_employee_cannot_upload_field_photos(): void
    {
        $employee = User::factory()->employee()->create([
            'team_leader_id' => $this->leader->id,
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
        ]);

        $this->actingAs($employee)
            ->post(route('employee.clock.in'), $this->withClockGps([
                'shift_type' => Attendance::SHIFT_FULL_TIME,
            ]))
            ->assertRedirect();

        $this->actingAs($employee)
            ->post(route('employee.clock.field-photos'), $this->withRequiredPunchPhoto())
            ->assertForbidden();
    }

    public function test_driver_blocked_by_missing_daily_report_like_employee(): void
    {
        $service = app(AttendanceService::class);
        $service->clockIn($this->driver, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-08-03 10:00:00'));
        $service->clockOut($this->driver, Carbon::parse('2026-08-03 18:00:00'));

        Carbon::setTestNow(Carbon::parse('2026-08-04 10:15:00', config('app.timezone')));

        $this->actingAs($this->driver)
            ->from(route('employee.clock'))
            ->post(route('employee.clock.in'), $this->withRequiredPunchPhoto([
                'shift_type' => Attendance::SHIFT_FULL_TIME,
            ]))
            ->assertSessionHasErrors('shift_type');

        $this->assertSame(1, Attendance::where('user_id', $this->driver->id)->count());

        $this->actingAs($this->driver)
            ->get(route('employee.report', ['date' => '2026-08-03']))
            ->assertOk();

        $this->actingAs($this->driver)
            ->post(route('employee.report.store'), [
                'date' => '2026-08-03',
                'daily_report' => 'Delivered packages across the city.',
            ])
            ->assertRedirect();

        $this->actingAs($this->driver)
            ->post(route('employee.clock.in'), $this->withRequiredPunchPhoto([
                'shift_type' => Attendance::SHIFT_FULL_TIME,
            ]))
            ->assertRedirect(route('employee.clock'));

        $this->assertSame(2, Attendance::where('user_id', $this->driver->id)->count());
    }

    public function test_clock_screen_marks_photos_and_location_required_for_driver(): void
    {
        $this->actingAs($this->driver)
            ->get(route('employee.clock'))
            ->assertOk()
            ->assertSee(__('clock.photos.required'), false)
            ->assertSee('data-required="1"', false)
            ->assertSee('data-location-always="1"', false);
    }

    public function test_driver_is_blocked_from_leave_salary_and_evaluation(): void
    {
        $this->actingAs($this->driver)
            ->get(route('employee.requests'))
            ->assertForbidden();

        $this->actingAs($this->driver)
            ->get(route('employee.evaluation'))
            ->assertForbidden();

        $this->actingAs($this->driver)
            ->get(route('employee.salary'))
            ->assertForbidden();

        $this->actingAs($this->driver)
            ->get(route('employee.dashboard'))
            ->assertForbidden();
    }

    public function test_driver_full_time_still_requires_gps(): void
    {
        $this->actingAs($this->driver)
            ->from(route('employee.clock'))
            ->post(route('employee.clock.in'), [
                'shift_type' => Attendance::SHIFT_FULL_TIME,
                'photos' => [UploadedFile::fake()->image('gate.jpg', 200, 200)],
                'photo_descriptions' => ['At the gate'],
            ])
            ->assertSessionHasErrors(['latitude', 'longitude']);
    }

    public function test_driver_online_half_also_requires_gps_and_photos(): void
    {
        $this->driver->update([
            'shift_types_default' => [
                ['type' => Attendance::SHIFT_ONLINE_HALF, 'start' => '06:00', 'end' => '10:00'],
            ],
            'shift_type_default' => Attendance::SHIFT_ONLINE_HALF,
            'work_start_time' => '06:00',
            'work_end_time' => '10:00',
        ]);

        Carbon::setTestNow(Carbon::parse('2026-08-03 07:00:00', config('app.timezone')));

        $this->actingAs($this->driver)
            ->from(route('employee.clock'))
            ->post(route('employee.clock.in'), [
                'shift_type' => Attendance::SHIFT_ONLINE_HALF,
                'photos' => [UploadedFile::fake()->image('home.jpg', 200, 200)],
                'photo_descriptions' => ['Starting online half'],
            ])
            ->assertSessionHasErrors(['latitude', 'longitude']);

        $this->actingAs($this->driver)
            ->post(route('employee.clock.in'), $this->withClockGps([
                'shift_type' => Attendance::SHIFT_ONLINE_HALF,
                'photos' => [UploadedFile::fake()->image('home.jpg', 200, 200)],
                'photo_descriptions' => ['Starting online half'],
            ]))
            ->assertRedirect(route('employee.clock'));

        $attendance = Attendance::where('user_id', $this->driver->id)->firstOrFail();
        $this->assertSame(Attendance::SHIFT_ONLINE_HALF, $attendance->shift_type);
        $this->assertNotNull($attendance->clock_in_lat);
    }

    public function test_driver_clock_out_requires_photo(): void
    {
        $this->actingAs($this->driver)
            ->post(route('employee.clock.in'), $this->withRequiredPunchPhoto([
                'shift_type' => Attendance::SHIFT_FULL_TIME,
            ]))
            ->assertRedirect();

        Carbon::setTestNow(Carbon::parse('2026-08-03 18:00:00', config('app.timezone')));

        $this->actingAs($this->driver)
            ->from(route('employee.clock'))
            ->post(route('employee.clock.out'), $this->withClockGps())
            ->assertSessionHasErrors('photos');

        $this->assertNull(Attendance::where('user_id', $this->driver->id)->value('clock_out'));
    }

    public function test_driver_rejects_photo_without_matching_description(): void
    {
        $this->actingAs($this->driver)
            ->from(route('employee.clock'))
            ->post(route('employee.clock.in'), $this->withClockGps([
                'shift_type' => Attendance::SHIFT_FULL_TIME,
                'photos' => [
                    UploadedFile::fake()->image('a.jpg', 200, 200),
                    UploadedFile::fake()->image('b.jpg', 200, 200),
                ],
                'photo_descriptions' => ['Only first caption'],
            ]))
            ->assertSessionHasErrors();

        $this->assertSame(0, Attendance::where('user_id', $this->driver->id)->count());
    }

    public function test_store_requires_team_leader_for_driver(): void
    {
        $this->actingAs($this->manager)
            ->post(route('admin.employees.store'), $this->driverPayload([
                'email' => 'no.tl.driver@hrpro.test',
                'team_leader_ids' => [],
            ]))
            ->assertSessionHasErrors('team_leader_ids.0');
    }

    public function test_other_team_leader_does_not_see_driver(): void
    {
        $otherLeader = User::factory()->teamLeader()->create();

        $visible = app(TeamMembershipService::class)->visibleEmployees($otherLeader);
        $this->assertFalse($visible->contains(fn (User $u) => $u->is($this->driver)));
    }

    public function test_team_leader_can_assign_shift_and_rest_day_to_driver(): void
    {
        $shifts = app(ShiftAssignmentService::class);
        $this->assertTrue($shifts->actorMayAssignTo($this->leader, $this->driver));

        $rest = app(RestDayService::class);
        $this->assertTrue($rest->actorMayManage($this->leader, $this->driver));

        $this->actingAs($this->leader)
            ->post(route('team-leader.shifts.store'), [
                'user_id' => $this->driver->id,
                'date' => '2026-08-10',
                'shift_type' => Attendance::SHIFT_FULL_TIME,
                'expected_start_time' => '10:00',
                'expected_end_time' => '18:00',
                'expected_hours' => 8,
                'multiplier' => 1,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('shift_assignments', [
            'user_id' => $this->driver->id,
            'shift_type' => Attendance::SHIFT_FULL_TIME,
            'assigned_by_tl_id' => $this->leader->id,
        ]);

        $this->actingAs($this->leader)
            ->post(route('team-leader.rest-days.store', $this->driver), [
                'dates' => ['2026-08-14'],
            ])
            ->assertRedirect(route('team-leader.rest-days'));

        $this->assertTrue(
            RestDay::query()
                ->where('user_id', $this->driver->id)
                ->whereDate('date', '2026-08-14')
                ->exists()
        );
    }

    public function test_payroll_generates_draft_for_driver(): void
    {
        $record = app(PayrollGenerator::class)->generateForUser(
            $this->driver,
            8,
            2026,
            $this->manager->id,
        );

        $this->assertInstanceOf(PayrollRecord::class, $record);
        $this->assertSame($this->driver->id, $record->user_id);
        $this->assertSame(PayrollRecord::STATUS_DRAFT, $record->status);
    }

    public function test_create_form_lists_driver_role_for_manager_and_assistant(): void
    {
        $this->actingAs($this->manager)
            ->get(route('admin.employees.create'))
            ->assertOk()
            ->assertSee('value="DRIVER"', false)
            ->assertSee(__('employees.roles.DRIVER'), false);

        $this->actingAs($this->assistant)
            ->get(route('admin.employees.create'))
            ->assertOk()
            ->assertSee('value="DRIVER"', false);
    }
}
