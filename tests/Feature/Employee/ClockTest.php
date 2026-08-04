<?php

namespace Tests\Feature\Employee;

use App\Models\Attendance;
use App\Models\ShiftAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Gate 2.3–2.5 — employee clock screen happy path, 422 rules, FT→Online.
 */
class ClockTest extends TestCase
{
    use RefreshDatabase;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->employee = User::factory()->employee()->create([
            'shift_type_default' => Attendance::SHIFT_FULL_TIME,
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
        ]);
    }

    public function test_employee_can_open_clock_screen(): void
    {
        $this->actingAs($this->employee)
            ->get(route('employee.clock'))
            ->assertOk()
            ->assertSee(__('clock.clock_in'), false)
            ->assertSee(__('clock.shifts.FULL_TIME'), false)
            ->assertSee(__('clock.shifts.ONLINE_HALF'), false)
            ->assertSee(__('clock.shifts.ONLINE_FULL'), false)
            ->assertDontSee('value="OVERTIME"', false)
            ->assertDontSee('value="EXTRA_SHIFT"', false);
    }

    public function test_clock_in_and_clock_out_happy_path(): void
    {
        $this->actingAs($this->employee)
            ->post(route('employee.clock.in'), ['shift_type' => Attendance::SHIFT_FULL_TIME])
            ->assertRedirect(route('employee.clock'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('attendance', [
            'user_id' => $this->employee->id,
            'shift_type' => Attendance::SHIFT_FULL_TIME,
        ]);
        $this->assertNotNull(Attendance::where('user_id', $this->employee->id)->value('clock_in'));
        $this->assertNull(Attendance::where('user_id', $this->employee->id)->value('clock_out'));

        $this->actingAs($this->employee)
            ->get(route('employee.clock'))
            ->assertOk()
            ->assertSee(__('clock.clock_out'), false)
            ->assertSee('id="clock-elapsed"', false);

        $this->actingAs($this->employee)
            ->post(route('employee.clock.out'))
            ->assertRedirect(route('employee.clock'));

        $row = Attendance::where('user_id', $this->employee->id)->firstOrFail();
        $this->assertNotNull($row->clock_out);
    }

    public function test_second_open_clock_in_returns_validation_error(): void
    {
        $this->actingAs($this->employee)
            ->post(route('employee.clock.in'), ['shift_type' => Attendance::SHIFT_FULL_TIME])
            ->assertRedirect();

        $this->actingAs($this->employee)
            ->from(route('employee.clock'))
            ->post(route('employee.clock.in'), ['shift_type' => Attendance::SHIFT_ONLINE_HALF])
            ->assertRedirect(route('employee.clock'))
            ->assertSessionHasErrors('shift_type');

        $this->assertSame(1, Attendance::where('user_id', $this->employee->id)->count());
    }

    public function test_overtime_without_assignment_is_rejected(): void
    {
        $this->actingAs($this->employee)
            ->from(route('employee.clock'))
            ->post(route('employee.clock.in'), ['shift_type' => Attendance::SHIFT_OVERTIME])
            ->assertRedirect(route('employee.clock'))
            ->assertSessionHasErrors('shift_type');

        $this->assertSame(0, Attendance::count());
    }

    public function test_overtime_with_assignment_is_allowed_and_listed(): void
    {
        $leader = User::factory()->teamLeader()->create();
        $this->employee->update(['team_leader_id' => $leader->id]);

        ShiftAssignment::factory()->create([
            'user_id' => $this->employee->id,
            'assigned_by_tl_id' => $leader->id,
            'date' => now()->toDateString(),
            'shift_type' => ShiftAssignment::TYPE_OVERTIME,
            'status' => ShiftAssignment::STATUS_SCHEDULED,
        ]);

        $this->actingAs($this->employee)
            ->get(route('employee.clock'))
            ->assertOk()
            ->assertSee(__('clock.shifts.OVERTIME'), false);

        $this->actingAs($this->employee)
            ->post(route('employee.clock.in'), ['shift_type' => Attendance::SHIFT_OVERTIME])
            ->assertRedirect(route('employee.clock'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('attendance', [
            'user_id' => $this->employee->id,
            'shift_type' => Attendance::SHIFT_OVERTIME,
        ]);
    }

    public function test_employee_can_switch_ft_to_online_after_clock_out(): void
    {
        $this->actingAs($this->employee)
            ->post(route('employee.clock.in'), ['shift_type' => Attendance::SHIFT_FULL_TIME])
            ->assertRedirect();

        $this->actingAs($this->employee)
            ->post(route('employee.clock.out'))
            ->assertRedirect();

        $this->actingAs($this->employee)
            ->post(route('employee.clock.in'), ['shift_type' => Attendance::SHIFT_ONLINE_HALF])
            ->assertRedirect(route('employee.clock'));

        $this->assertSame(2, Attendance::where('user_id', $this->employee->id)->count());
        $this->assertDatabaseHas('attendance', [
            'user_id' => $this->employee->id,
            'shift_type' => Attendance::SHIFT_ONLINE_HALF,
        ]);
    }

    public function test_team_leader_can_access_clock(): void
    {
        $leader = User::factory()->teamLeader()->create([
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
            'shift_type_default' => Attendance::SHIFT_FULL_TIME,
        ]);

        $this->actingAs($leader)
            ->get(route('employee.clock'))
            ->assertOk()
            ->assertSee(__('clock.clock_in'), false);
    }

    public function test_manager_can_clock_in(): void
    {
        $manager = User::factory()->manager()->create([
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
            'shift_type_default' => Attendance::SHIFT_FULL_TIME,
        ]);

        $this->actingAs($manager)
            ->post(route('employee.clock.in'), ['shift_type' => Attendance::SHIFT_FULL_TIME])
            ->assertRedirect(route('employee.clock'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('attendance', [
            'user_id' => $manager->id,
            'shift_type' => Attendance::SHIFT_FULL_TIME,
        ]);
    }

    public function test_tl_assignment_is_preferred_but_employee_may_switch_to_online(): void
    {
        $leader = User::factory()->teamLeader()->create();
        $this->employee->update([
            'team_leader_id' => $leader->id,
            'shift_type_default' => Attendance::SHIFT_FULL_TIME,
        ]);

        ShiftAssignment::factory()->fullTime()->create([
            'user_id' => $this->employee->id,
            'assigned_by_tl_id' => $leader->id,
            'date' => now()->toDateString(),
        ]);

        $this->actingAs($this->employee)
            ->get(route('employee.clock'))
            ->assertOk()
            ->assertSee(__('clock.assigned_today', [
                'shift' => __('clock.shifts.FULL_TIME'),
            ]), false)
            ->assertSee('selected', false);

        $html = $this->actingAs($this->employee)
            ->get(route('employee.clock'))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/option value="FULL_TIME"[^>]*selected/s',
            $html,
        );

        $this->actingAs($this->employee)
            ->post(route('employee.clock.in'), ['shift_type' => Attendance::SHIFT_ONLINE_HALF])
            ->assertRedirect(route('employee.clock'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('attendance', [
            'user_id' => $this->employee->id,
            'shift_type' => Attendance::SHIFT_ONLINE_HALF,
            'planned_hours' => '4.00',
        ]);
    }

    public function test_without_assignment_prefers_profile_default(): void
    {
        $this->employee->update(['shift_type_default' => Attendance::SHIFT_ONLINE_FULL]);

        $html = $this->actingAs($this->employee)
            ->get(route('employee.clock'))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/option value="ONLINE_FULL"[^>]*selected/s',
            $html,
        );
        $this->assertStringNotContainsString(__('clock.assigned_today', [
            'shift' => __('clock.shifts.FULL_TIME'),
        ]), $html);
    }

    public function test_completed_full_time_is_hidden_and_rejected_same_day(): void
    {
        $this->actingAs($this->employee)
            ->post(route('employee.clock.in'), ['shift_type' => Attendance::SHIFT_FULL_TIME])
            ->assertRedirect();
        $this->actingAs($this->employee)
            ->post(route('employee.clock.out'))
            ->assertRedirect();

        $html = $this->actingAs($this->employee)
            ->get(route('employee.clock'))
            ->assertOk()
            ->getContent();

        $this->assertDoesNotMatchRegularExpression('/option value="FULL_TIME"/', $html);
        $this->assertMatchesRegularExpression('/option value="ONLINE_HALF"/', $html);

        $this->actingAs($this->employee)
            ->from(route('employee.clock'))
            ->post(route('employee.clock.in'), ['shift_type' => Attendance::SHIFT_FULL_TIME])
            ->assertRedirect(route('employee.clock'))
            ->assertSessionHasErrors('shift_type');
    }
}
