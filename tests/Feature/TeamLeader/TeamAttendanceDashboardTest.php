<?php

namespace Tests\Feature\TeamLeader;

use App\Models\Attendance;
use App\Models\RestDay;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Services\Attendance\HoursComparisonService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Gate 2.11 / T-2.13 — TL team attendance dashboard planned vs actual.
 */
class TeamAttendanceDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $leader;

    private User $employee;

    private User $otherEmployee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->leader = User::factory()->teamLeader()->create([
            'language_preference' => 'en',
        ]);
        $this->employee = User::factory()->employee()->create([
            'team_leader_id' => $this->leader->id,
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
            'name_en' => 'Radwa Test',
            'language_preference' => 'en',
        ]);
        $otherLeader = User::factory()->teamLeader()->create();
        $this->otherEmployee = User::factory()->employee()->create([
            'team_leader_id' => $otherLeader->id,
            'name_en' => 'Other Team Person',
            'work_start_time' => '09:00',
            'work_end_time' => '17:00',
        ]);
    }

    public function test_team_leader_sees_today_board_and_own_team_only(): void
    {
        app()->setLocale('en');

        ShiftAssignment::factory()->create([
            'user_id' => $this->employee->id,
            'date' => now()->toDateString(),
            'shift_type' => Attendance::SHIFT_FULL_TIME,
            'expected_hours' => 8,
            'expected_start_time' => '10:00',
            'expected_end_time' => '18:00',
            'status' => ShiftAssignment::STATUS_SCHEDULED,
        ]);

        $this->actingAs($this->leader)
            ->get(route('team-leader.dashboard'))
            ->assertOk()
            ->assertSee(__('dashboard.hours.today_board'), false)
            ->assertSee(__('dashboard.hours.team_month'), false)
            ->assertSee('Radwa Test', false)
            ->assertDontSee('Other Team Person', false)
            ->assertSee(__('dashboard.hours.should_spend'), false)
            ->assertSee('data-table-filter-root', false)
            ->assertSee('table-hr', false)
            ->assertSee('data-table-search', false);
    }

    public function test_team_leader_cannot_focus_other_team_employee(): void
    {
        $this->actingAs($this->leader)
            ->get(route('team-leader.dashboard', ['user_id' => $this->otherEmployee->id]))
            ->assertForbidden();
    }

    public function test_employee_dashboard_shows_month_planned_and_day_list(): void
    {
        app()->setLocale('en');

        ShiftAssignment::factory()->create([
            'user_id' => $this->employee->id,
            'date' => '2026-08-05',
            'shift_type' => Attendance::SHIFT_FULL_TIME,
            'expected_hours' => 8,
            'expected_start_time' => '10:00',
            'expected_end_time' => '18:00',
            'status' => ShiftAssignment::STATUS_SCHEDULED,
        ]);

        Attendance::factory()->create([
            'user_id' => $this->employee->id,
            'date' => '2026-08-05',
            'shift_type' => Attendance::SHIFT_FULL_TIME,
            'clock_in' => '2026-08-05 10:00:00',
            'clock_out' => '2026-08-05 18:00:00',
            'actual_hours' => 8,
            'planned_hours' => 8,
        ]);

        $this->actingAs($this->employee)
            ->get(route('employee.dashboard', ['cal' => '2026-08']))
            ->assertOk()
            ->assertSee(__('dashboard.hours.month_planned'), false)
            ->assertSee(__('dashboard.hours.day_list_title'), false)
            ->assertSee('2026-08-05', false);
    }

    public function test_team_leader_defaults_to_own_calendar_me(): void
    {
        app()->setLocale('en');

        $this->leader->update([
            'work_start_time' => '09:00',
            'work_end_time' => '17:00',
            'name_en' => 'Team Leader',
        ]);

        $this->actingAs($this->leader)
            ->withSession(['locale' => 'en'])
            ->get(route('team-leader.dashboard', ['cal' => '2026-08']))
            ->assertOk()
            ->assertSee(__('dashboard.hours.focus_me'), false)
            ->assertSee(__('dashboard.hours.calendar_for', [
                'name' => __('dashboard.hours.focus_me'),
            ]), false)
            ->assertSee(__('dashboard.hours.day_list_for', [
                'name' => __('dashboard.hours.focus_me'),
            ]), false);
    }

    public function test_team_leader_can_switch_focus_to_team_member(): void
    {
        app()->setLocale('en');

        $this->actingAs($this->leader)
            ->withSession(['locale' => 'en'])
            ->get(route('team-leader.dashboard', [
                'cal' => '2026-08',
                'user_id' => $this->employee->id,
            ]))
            ->assertOk()
            ->assertSee(__('dashboard.hours.calendar_for', ['name' => 'Radwa Test']), false);
    }

    public function test_rest_day_shows_on_dashboard_calendar_and_day_list(): void
    {
        app()->setLocale('en');

        // Date-based rest day on Friday 2026-08-07.
        RestDay::factory()->create([
            'user_id' => $this->employee->id,
            'date' => '2026-08-07',
            'created_by' => $this->leader->id,
        ]);

        $this->actingAs($this->leader)
            ->withSession(['locale' => 'en'])
            ->get(route('team-leader.dashboard', [
                'cal' => '2026-08',
                'user_id' => $this->employee->id,
            ]))
            ->assertOk()
            ->assertSee(__('dashboard.hours.rest_day'), false)
            ->assertSee(__('dashboard.calendar.legend_rest_day'), false)
            ->assertSee('2026-08-07', false);

        $month = app(HoursComparisonService::class)
            ->monthForUser($this->employee, '2026-08');
        $day = collect($month['days'])->firstWhere('date', '2026-08-07');
        $this->assertTrue($day['is_rest_day']);
        $this->assertSame(0.0, $day['planned_hours']);
    }

    public function test_changing_assignment_updates_planned_on_dashboard(): void
    {
        $assignment = ShiftAssignment::factory()->create([
            'user_id' => $this->employee->id,
            'date' => '2026-08-05',
            'shift_type' => Attendance::SHIFT_FULL_TIME,
            'expected_hours' => 8,
            'expected_start_time' => '10:00',
            'expected_end_time' => '18:00',
            'status' => ShiftAssignment::STATUS_SCHEDULED,
        ]);

        $this->actingAs($this->leader)
            ->get(route('team-leader.dashboard', [
                'cal' => '2026-08',
                'user_id' => $this->employee->id,
            ]))
            ->assertOk()
            ->assertSee('8.00h', false);

        $assignment->update([
            'shift_type' => Attendance::SHIFT_ONLINE_HALF,
            'expected_hours' => 4,
            'expected_end_time' => '14:00',
        ]);

        $this->actingAs($this->leader)
            ->get(route('team-leader.dashboard', [
                'cal' => '2026-08',
                'user_id' => $this->employee->id,
            ]))
            ->assertOk()
            ->assertSee('4.00h', false);
    }
}
