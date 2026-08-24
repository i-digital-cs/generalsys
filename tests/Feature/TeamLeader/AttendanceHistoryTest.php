<?php

namespace Tests\Feature\TeamLeader;

use App\Models\Attendance;
use App\Models\AttendancePhoto;
use App\Models\RestDay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T-7.6 — attendance history: person, dates, absent, photos, GPS.
 */
class AttendanceHistoryTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $assistant;

    private User $leader;

    private User $member;

    private User $outsider;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('en');

        $this->manager = User::factory()->manager()->create();
        $this->assistant = User::factory()->assistant()->create();
        $this->leader = User::factory()->teamLeader()->create();
        $this->member = User::factory()->employee()->create([
            'team_leader_id' => $this->leader->id,
            'name_en' => 'History Member',
            'name_ar' => 'عضو السجل',
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
        ]);
        $this->outsider = User::factory()->employee()->create([
            'name_en' => 'History Outsider',
            'name_ar' => 'خارج الفريق',
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
        ]);
    }

    /** @return array<string, mixed> */
    private function query(array $overrides = []): array
    {
        return array_merge([
            'from' => '2026-08-10',
            'to' => '2026-08-10',
            'kind' => 'all',
            'evidence' => 'all',
        ], $overrides);
    }

    public function test_manager_and_assistant_can_open_history(): void
    {
        $this->actingAs($this->manager)
            ->get(route('team-leader.attendance', $this->query()))
            ->assertOk()
            ->assertSee(__('attendance.history.title'), false)
            ->assertSee(__('attendance.history.hours_column'), false)
            ->assertSee(__('attendance.history.minutes_column'), false)
            ->assertSee(__('attendance.history.open_details'), false)
            ->assertSee('History Member', false)
            ->assertSee('History Outsider', false)
            ->assertSee(route('admin.employees.edit', $this->member), false);

        $this->actingAs($this->assistant)
            ->get(route('team-leader.attendance', $this->query()))
            ->assertOk()
            ->assertSee('History Member', false)
            ->assertSee(route('admin.employees.edit', $this->member), false);
    }

    public function test_team_leader_name_links_to_attendance_filter_not_edit(): void
    {
        $this->actingAs($this->leader)
            ->get(route('team-leader.attendance', $this->query()))
            ->assertOk()
            ->assertSee('employee_id='.$this->member->id, false)
            ->assertDontSee(route('admin.employees.edit', $this->member), false);
    }

    public function test_team_leader_sees_only_own_team(): void
    {
        $this->actingAs($this->leader)
            ->get(route('team-leader.attendance', $this->query()))
            ->assertOk()
            ->assertSee('History Member', false)
            ->assertDontSee('History Outsider', false);

        $this->actingAs($this->leader)
            ->get(route('team-leader.attendance', $this->query([
                'employee_id' => $this->outsider->id,
            ])))
            ->assertForbidden();
    }

    public function test_employee_and_accountant_are_blocked(): void
    {
        $this->actingAs($this->member)
            ->get(route('team-leader.attendance'))
            ->assertForbidden();

        $accountant = User::factory()->accountant()->create();
        $this->actingAs($accountant)
            ->get(route('team-leader.attendance'))
            ->assertForbidden();
    }

    public function test_absent_filter_shows_work_day_without_punch(): void
    {
        $this->actingAs($this->manager)
            ->get(route('team-leader.attendance', $this->query([
                'employee_id' => $this->member->id,
                'kind' => 'absent',
            ])))
            ->assertOk()
            ->assertSee('History Member', false)
            ->assertSee(__('attendance.history.kinds.absent'), false);
    }

    public function test_rest_day_is_not_listed_as_absent(): void
    {
        RestDay::query()->create([
            'user_id' => $this->member->id,
            'date' => '2026-08-10',
            'created_by' => $this->leader->id,
        ]);

        $this->actingAs($this->manager)
            ->get(route('team-leader.attendance', $this->query([
                'employee_id' => $this->member->id,
                'kind' => 'absent',
            ])))
            ->assertOk()
            ->assertViewHas('days', fn ($days) => $days->total() === 0);

        $this->actingAs($this->manager)
            ->get(route('team-leader.attendance', $this->query([
                'employee_id' => $this->member->id,
                'kind' => 'rest',
            ])))
            ->assertOk()
            ->assertViewHas('days', fn ($days) => $days->total() === 1
                && $days[0]['kind'] === 'rest');
    }

    public function test_photos_and_outside_filters(): void
    {
        $inside = Attendance::factory()->create([
            'user_id' => $this->member->id,
            'date' => '2026-08-10',
            'shift_type' => Attendance::SHIFT_FULL_TIME,
            'clock_in' => '2026-08-10 10:00:00',
            'clock_out' => '2026-08-10 18:00:00',
            'actual_hours' => 8,
            'planned_hours' => 8,
            'clock_in_lat' => 30.0444,
            'clock_in_lng' => 31.2357,
            'clock_in_distance_m' => 40,
            'clock_in_outside_geofence' => false,
            'clock_out_lat' => 30.0445,
            'clock_out_lng' => 31.2358,
            'clock_out_distance_m' => 55,
            'clock_out_outside_geofence' => false,
        ]);
        AttendancePhoto::query()->create([
            'attendance_id' => $inside->id,
            'kind' => AttendancePhoto::KIND_CLOCK_IN,
            'file_path' => 'attendance-photos/in.jpg',
            'original_name' => 'in.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 120,
            'sort_order' => 0,
            'uploaded_at' => now(),
        ]);

        $this->actingAs($this->manager)
            ->get(route('team-leader.attendance', $this->query([
                'employee_id' => $this->member->id,
                'evidence' => 'photos',
            ])))
            ->assertOk()
            ->assertSee(route('attendance.photos.show', $inside->photos()->first()), false)
            ->assertSee(__('clock.location.badge_status', [
                'punch' => __('clock.location.punch_in'),
                'status' => __('clock.location.inside'),
            ]), false)
            ->assertSee(__('clock.location.badge_status', [
                'punch' => __('clock.location.punch_out'),
                'status' => __('clock.location.inside'),
            ]), false)
            ->assertSee(__('clock.shifts.FULL_TIME'), false)
            ->assertSee(__('clock.location.office_expected'), false);

        Attendance::factory()->create([
            'user_id' => $this->member->id,
            'date' => '2026-08-11',
            'shift_type' => Attendance::SHIFT_ONLINE_HALF,
            'clock_in' => '2026-08-11 06:00:00',
            'clock_out' => '2026-08-11 10:00:00',
            'actual_hours' => 4,
            'planned_hours' => 4,
            'clock_in_lat' => 29.0000,
            'clock_in_lng' => 31.0000,
            'clock_in_outside_geofence' => true,
            'clock_out_lat' => 29.0001,
            'clock_out_lng' => 31.0001,
            'clock_out_outside_geofence' => true,
        ]);

        $this->actingAs($this->manager)
            ->get(route('team-leader.attendance', $this->query([
                'employee_id' => $this->member->id,
                'from' => '2026-08-11',
                'to' => '2026-08-11',
            ])))
            ->assertOk()
            ->assertSee(__('clock.shifts.ONLINE_HALF'), false)
            ->assertSee(__('clock.location.office_optional'), false)
            ->assertSee(__('clock.location.outside_ok'), false)
            ->assertDontSee(__('clock.location.badge_status', [
                'punch' => __('clock.location.punch_in'),
                'status' => __('clock.location.outside'),
            ]), false);

        Attendance::factory()->create([
            'user_id' => $this->outsider->id,
            'date' => '2026-08-10',
            'shift_type' => Attendance::SHIFT_FULL_TIME,
            'clock_in' => '2026-08-10 10:00:00',
            'clock_out' => '2026-08-10 18:00:00',
            'actual_hours' => 8,
            'clock_in_lat' => 29.0000,
            'clock_in_lng' => 31.0000,
            'clock_in_outside_geofence' => true,
        ]);

        $this->actingAs($this->manager)
            ->get(route('team-leader.attendance', $this->query([
                'kind' => 'present',
                'evidence' => 'outside',
            ])))
            ->assertOk()
            ->assertSee(__('clock.location.outside'), false)
            ->assertViewHas('days', function ($days): bool {
                return $days->total() === 1
                    && $days[0]['user']->is($this->outsider)
                    && $days[0]['outside'] === true;
            });
    }

    public function test_page_two_keeps_filters_and_does_not_go_empty(): void
    {
        User::factory()->employee()->count(30)->create([
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
        ]);

        $this->actingAs($this->manager)
            ->get(route('team-leader.attendance', $this->query()))
            ->assertOk()
            ->assertSee('pagination', false)
            ->assertSee('page=2', false)
            ->assertSee('from=2026-08-10', false)
            ->assertSee('to=2026-08-10', false)
            ->assertViewHas('days', function ($days): bool {
                return $days->currentPage() === 1
                    && $days->perPage() === 25
                    && $days->count() === 25
                    && $days->total() > 25;
            });

        $this->actingAs($this->manager)
            ->get(route('team-leader.attendance', $this->query(['page' => 2])))
            ->assertOk()
            ->assertDontSee(__('attendance.history.empty_title'), false)
            ->assertViewHas('days', function ($days): bool {
                return $days->currentPage() === 2
                    && $days->count() > 0
                    && $days->count() <= 25;
            });

        $this->actingAs($this->manager)
            ->get(route('team-leader.attendance', $this->query(['page' => 99])))
            ->assertOk()
            ->assertDontSee(__('attendance.history.empty_title'), false)
            ->assertViewHas('days', function ($days): bool {
                return $days->currentPage() === $days->lastPage()
                    && $days->isNotEmpty();
            });
    }

    public function test_range_longer_than_92_days_is_rejected(): void
    {
        $this->actingAs($this->manager)
            ->from(route('team-leader.attendance'))
            ->get(route('team-leader.attendance', [
                'from' => '2026-01-01',
                'to' => '2026-05-01',
            ]))
            ->assertRedirect()
            ->assertSessionHasErrors('to');
    }
}
