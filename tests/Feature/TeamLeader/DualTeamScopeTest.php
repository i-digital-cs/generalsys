<?php

namespace Tests\Feature\TeamLeader;

use App\Models\Attendance;
use App\Models\AttendancePhoto;
use App\Models\RestDay;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Notifications\AttendanceGeofenceWarningNotification;
use App\Services\Admin\SettingService;
use App\Services\Attendance\AttendanceService;
use App\Services\Attendance\MissingDailyReportService;
use App\Services\Employees\TeamMembershipService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * T-8.3 — both TLs see/act on the same member; a third TL is 403 / hidden.
 */
class DualTeamScopeTest extends TestCase
{
    use RefreshDatabase;

    private User $leaderA;

    private User $leaderB;

    private User $leaderC;

    private User $member;

    private User $outsider;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('en');

        $this->leaderA = User::factory()->teamLeader()->create(['name_en' => 'Leader A Dual']);
        $this->leaderB = User::factory()->teamLeader()->create(['name_en' => 'Leader B Dual']);
        $this->leaderC = User::factory()->teamLeader()->create(['name_en' => 'Leader C Dual']);
        $this->member = User::factory()->employee()->create([
            'name_en' => 'Dual Scope Member',
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
            'shift_type_default' => Attendance::SHIFT_FULL_TIME,
        ]);
        $this->outsider = User::factory()->employee()->create([
            'team_leader_id' => $this->leaderC->id,
            'name_en' => 'Dual Scope Outsider',
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
            'shift_type_default' => Attendance::SHIFT_FULL_TIME,
        ]);

        app(TeamMembershipService::class)->syncLeaders($this->member, [
            $this->leaderA->id,
            $this->leaderB->id,
        ]);
    }

    public function test_both_leaders_see_member_on_shifts_third_does_not(): void
    {
        $this->actingAs($this->leaderA)
            ->get(route('team-leader.shifts'))
            ->assertOk()
            ->assertSee('Dual Scope Member', false)
            ->assertDontSee('Dual Scope Outsider', false);

        $this->actingAs($this->leaderB)
            ->get(route('team-leader.shifts'))
            ->assertOk()
            ->assertSee('Dual Scope Member', false)
            ->assertDontSee('Dual Scope Outsider', false);

        $this->actingAs($this->leaderC)
            ->get(route('team-leader.shifts'))
            ->assertOk()
            ->assertSee('Dual Scope Outsider', false)
            ->assertDontSee('Dual Scope Member', false);
    }

    public function test_either_leader_can_assign_a_shift_third_is_forbidden(): void
    {
        $payload = [
            'user_id' => $this->member->id,
            'date' => '2026-08-18',
            'shift_type' => ShiftAssignment::TYPE_FULL_TIME,
            'expected_start_time' => '10:00',
            'expected_end_time' => '18:00',
            'expected_hours' => 8,
            'multiplier' => 1,
        ];

        $this->actingAs($this->leaderA)
            ->post(route('team-leader.shifts.store'), $payload)
            ->assertRedirect(route('team-leader.shifts', ['date' => '2026-08-18']));

        $this->assertDatabaseHas('shift_assignments', [
            'user_id' => $this->member->id,
            'assigned_by_tl_id' => $this->leaderA->id,
            'shift_type' => ShiftAssignment::TYPE_FULL_TIME,
        ]);

        $this->actingAs($this->leaderC)
            ->post(route('team-leader.shifts.store'), array_merge($payload, [
                'date' => '2026-08-19',
            ]))
            ->assertSessionHasErrors('user_id');

        $assignment = ShiftAssignment::query()
            ->where('user_id', $this->member->id)
            ->where('assigned_by_tl_id', $this->leaderA->id)
            ->firstOrFail();

        $this->actingAs($this->leaderC)
            ->post(route('team-leader.shifts.cancel', $assignment))
            ->assertForbidden();

        $this->actingAs($this->leaderB)
            ->post(route('team-leader.shifts.store'), array_merge($payload, [
                'date' => '2026-08-20',
            ]))
            ->assertRedirect(route('team-leader.shifts', ['date' => '2026-08-20']));

        $this->assertDatabaseHas('shift_assignments', [
            'user_id' => $this->member->id,
            'assigned_by_tl_id' => $this->leaderB->id,
            'shift_type' => ShiftAssignment::TYPE_FULL_TIME,
        ]);
    }

    public function test_either_leader_can_add_rest_days_third_is_forbidden(): void
    {
        $this->actingAs($this->leaderA)
            ->get(route('team-leader.rest-days'))
            ->assertOk()
            ->assertSee('Dual Scope Member', false)
            ->assertDontSee('Dual Scope Outsider', false);

        $this->actingAs($this->leaderB)
            ->post(route('team-leader.rest-days.store', $this->member), [
                'dates' => ['2026-08-14'],
            ])
            ->assertRedirect(route('team-leader.rest-days'));

        $this->assertDatabaseHas('rest_days', [
            'user_id' => $this->member->id,
            'created_by' => $this->leaderB->id,
        ]);
        $this->assertTrue(
            RestDay::query()
                ->where('user_id', $this->member->id)
                ->whereDate('date', '2026-08-14')
                ->exists()
        );

        $this->actingAs($this->leaderC)
            ->post(route('team-leader.rest-days.store', $this->member), [
                'dates' => ['2026-08-21'],
            ])
            ->assertForbidden();
    }

    public function test_either_leader_can_open_report_third_is_forbidden(): void
    {
        $row = $this->closedShiftWithReport($this->member, 'Dual day report');

        $this->actingAs($this->leaderA)
            ->get(route('team-leader.reports.show', $row))
            ->assertOk()
            ->assertSee('Dual day report', false);

        $this->actingAs($this->leaderB)
            ->get(route('team-leader.reports.show', $row))
            ->assertOk()
            ->assertSee('Dual day report', false);

        $this->actingAs($this->leaderC)
            ->get(route('team-leader.reports.show', $row))
            ->assertForbidden();

        $this->actingAs($this->leaderC)
            ->post(route('team-leader.reports.respond', $row), [
                'team_leader_response' => 'Nope',
            ])
            ->assertForbidden();
    }

    public function test_hours_board_and_history_include_member_for_both_leaders(): void
    {
        $this->actingAs($this->leaderA)
            ->get(route('team-leader.dashboard'))
            ->assertOk()
            ->assertSee('Dual Scope Member', false)
            ->assertDontSee('Dual Scope Outsider', false);

        $this->actingAs($this->leaderB)
            ->get(route('team-leader.dashboard'))
            ->assertOk()
            ->assertSee('Dual Scope Member', false);

        $this->actingAs($this->leaderC)
            ->get(route('team-leader.dashboard', ['user_id' => $this->member->id]))
            ->assertForbidden();

        $history = [
            'from' => '2026-08-10',
            'to' => '2026-08-10',
            'kind' => 'all',
            'evidence' => 'all',
        ];

        $this->actingAs($this->leaderA)
            ->get(route('team-leader.attendance', $history + ['employee_id' => $this->member->id]))
            ->assertOk()
            ->assertSee('Dual Scope Member', false);

        $this->actingAs($this->leaderB)
            ->get(route('team-leader.attendance', $history + ['employee_id' => $this->member->id]))
            ->assertOk();

        $this->actingAs($this->leaderC)
            ->get(route('team-leader.attendance', $history + ['employee_id' => $this->member->id]))
            ->assertForbidden();
    }

    public function test_both_leaders_can_view_punch_photo_third_cannot(): void
    {
        Storage::fake('local');

        $this->actingAs($this->member)
            ->post(route('employee.clock.in'), $this->withClockGps([
                'shift_type' => Attendance::SHIFT_FULL_TIME,
                'photos' => [UploadedFile::fake()->image('dual.jpg')],
            ]))
            ->assertRedirect(route('employee.clock'));

        $photo = AttendancePhoto::firstOrFail();

        $this->actingAs($this->leaderA)
            ->get(route('attendance.photos.show', $photo))
            ->assertOk();

        $this->actingAs($this->leaderB)
            ->get(route('attendance.photos.show', $photo))
            ->assertOk();

        $this->actingAs($this->leaderC)
            ->get(route('attendance.photos.show', $photo))
            ->assertForbidden();
    }

    public function test_geofence_warning_notifies_both_leaders(): void
    {
        Notification::fake();

        $settings = app(SettingService::class);
        $settings->set(SettingService::KEY_OFFICE_LATITUDE, '30.0444000');
        $settings->set(SettingService::KEY_OFFICE_LONGITUDE, '31.2357000');
        $settings->set(SettingService::KEY_OFFICE_RADIUS_METERS, '1000');

        $this->actingAs($this->member)
            ->post(route('employee.clock.in'), $this->withClockGps([
                'shift_type' => Attendance::SHIFT_FULL_TIME,
                'latitude' => 30.0890,
                'longitude' => 31.2357,
            ]))
            ->assertRedirect(route('employee.clock'));

        Notification::assertSentTo($this->leaderA, AttendanceGeofenceWarningNotification::class);
        Notification::assertSentTo($this->leaderB, AttendanceGeofenceWarningNotification::class);
        Notification::assertNotSentTo($this->leaderC, AttendanceGeofenceWarningNotification::class);
    }

    public function test_missing_report_alert_is_visible_to_both_leaders(): void
    {
        $date = Carbon::parse('2026-08-10');
        $service = app(MissingDailyReportService::class);

        $idsA = $service->missingForActor($this->leaderA, $date)->pluck('id');
        $idsB = $service->missingForActor($this->leaderB, $date)->pluck('id');
        $idsC = $service->missingForActor($this->leaderC, $date)->pluck('id');

        $this->assertTrue($idsA->contains($this->member->id));
        $this->assertTrue($idsB->contains($this->member->id));
        $this->assertFalse($idsC->contains($this->member->id));
        $this->assertTrue($idsC->contains($this->outsider->id));
        $this->assertFalse($idsA->contains($this->outsider->id));
    }

    private function closedShiftWithReport(User $user, string $report): Attendance
    {
        $service = app(AttendanceService::class);
        $service->clockIn($user, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-08-03 10:00:00'));
        $row = $service->clockOut($user, Carbon::parse('2026-08-03 18:00:00'));
        $row->update(['daily_report' => $report]);

        return $row->fresh();
    }
}
