<?php

namespace Tests\Feature\Employee;

use App\Models\Attendance;
use App\Models\ShiftAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** T-2.4 — employee dashboard today status + CTA to clock. */
class EmployeeDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->employee = User::factory()->employee()->create([
            'shift_type_default' => Attendance::SHIFT_FULL_TIME,
            'language_preference' => 'en',
        ]);
    }

    public function test_employee_can_open_dashboard_with_clock_cta(): void
    {
        app()->setLocale('en');

        $this->actingAs($this->employee)
            ->get(route('employee.dashboard'))
            ->assertOk()
            ->assertSee(__('dashboard.employee.title'), false)
            ->assertSee(__('dashboard.employee.go_clock_in'), false)
            ->assertSee(route('employee.clock'), false)
            ->assertSee(__('clock.shifts.FULL_TIME'), false);
    }

    public function test_dashboard_shows_open_punch_and_clock_out_cta(): void
    {
        app()->setLocale('en');
        $this->freezeClockToMorningShift();

        $this->actingAs($this->employee)
            ->post(route('employee.clock.in'), $this->withClockGps(['shift_type' => Attendance::SHIFT_FULL_TIME]));

        $this->actingAs($this->employee)
            ->get(route('employee.dashboard'))
            ->assertOk()
            ->assertSee(__('dashboard.employee.clocked_in'), false)
            ->assertSee(__('dashboard.employee.go_clock_out'), false)
            ->assertSee('data-table-filter-root', false)
            ->assertSee('table-hr', false)
            ->assertSee('data-table-search', false);
    }

    public function test_dashboard_reflects_tl_assignment(): void
    {
        app()->setLocale('en');

        $leader = User::factory()->teamLeader()->create();
        $this->employee->update(['team_leader_id' => $leader->id]);

        ShiftAssignment::factory()->onlineHalf()->create([
            'user_id' => $this->employee->id,
            'assigned_by_tl_id' => $leader->id,
            'date' => now()->toDateString(),
        ]);

        $this->actingAs($this->employee)
            ->get(route('employee.dashboard'))
            ->assertOk()
            ->assertSee(__('clock.shifts.ONLINE_HALF'), false)
            ->assertSee(__('dashboard.employee.assigned_by_tl'), false);
    }

    public function test_dashboard_lists_all_assigned_shifts_and_calendar(): void
    {
        app()->setLocale('en');

        $leader = User::factory()->teamLeader()->create();
        $this->employee->update(['team_leader_id' => $leader->id]);

        ShiftAssignment::factory()->fullTime()->create([
            'user_id' => $this->employee->id,
            'assigned_by_tl_id' => $leader->id,
            'date' => now()->toDateString(),
        ]);
        ShiftAssignment::factory()->onlineHalf()->create([
            'user_id' => $this->employee->id,
            'assigned_by_tl_id' => $leader->id,
            'date' => now()->addDay()->toDateString(),
        ]);

        $this->actingAs($this->employee)
            ->get(route('employee.dashboard'))
            ->assertOk()
            ->assertSee(__('dashboard.hours.day_list_title'), false)
            ->assertSee(__('dashboard.hours.month_planned'), false)
            ->assertSee(__('shifts.types.FULL_TIME'), false)
            ->assertSee(__('shifts.types.ONLINE_HALF'), false)
            ->assertSee(__('dashboard.calendar.title'), false);
    }

    public function test_team_leader_and_manager_cannot_open_employee_dashboard(): void
    {
        $this->actingAs(User::factory()->teamLeader()->create())
            ->get(route('employee.dashboard'))
            ->assertForbidden();

        $this->actingAs(User::factory()->manager()->create())
            ->get(route('employee.dashboard'))
            ->assertForbidden();
    }
}
