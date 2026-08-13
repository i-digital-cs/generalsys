<?php

namespace Tests\Feature\Employee;

use App\Models\Attendance;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Services\Attendance\AttendanceService;
use Carbon\Carbon;
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

    public function test_clock_in_blocked_and_banner_shown_when_previous_report_missing(): void
    {
        $service = app(AttendanceService::class);
        $service->clockIn($this->employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-08-03 10:00:00'));
        $service->clockOut($this->employee, Carbon::parse('2026-08-03 18:00:00'));

        Carbon::setTestNow(Carbon::parse('2026-08-04 10:00:00', config('app.timezone')));

        $this->actingAs($this->employee)
            ->get(route('employee.clock'))
            ->assertOk()
            ->assertSee(__('clock.report_missing', ['date' => '2026-08-03']));

        $this->actingAs($this->employee)
            ->from(route('employee.clock'))
            ->post(route('employee.clock.in'), $this->withClockGps(['shift_type' => Attendance::SHIFT_FULL_TIME]))
            ->assertSessionHasErrors('shift_type');

        $this->assertSame(1, Attendance::where('user_id', $this->employee->id)->count());
    }

    public function test_employee_can_open_clock_screen(): void
    {
        $this->actingAs($this->employee)
            ->get(route('employee.clock'))
            ->assertOk()
            ->assertSee(__('clock.clock_in'), false)
            ->assertSee(__('clock.shifts.FULL_TIME'), false)
            ->assertSee(__('clock.should_start_at', [
                'shift' => __('clock.shifts.FULL_TIME'),
                'time' => '10:00',
            ]), false)
            ->assertDontSee('value="ONLINE_HALF"', false)
            ->assertDontSee('value="ONLINE_FULL"', false)
            ->assertDontSee('value="OVERTIME"', false)
            ->assertDontSee('value="EXTRA_SHIFT"', false)
            ->assertSee(__('clock.rotation_hint'), false);
    }

    public function test_clock_in_and_clock_out_happy_path(): void
    {
        $this->actingAs($this->employee)
            ->post(route('employee.clock.in'), $this->withClockGps(['shift_type' => Attendance::SHIFT_FULL_TIME]))
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
            ->assertSee('id="clock-elapsed"', false)
            ->assertSee(__('clock.late_minutes'), false);

        // Missing daily report → routed to the report page for that date.
        $this->actingAs($this->employee)
            ->post(route('employee.clock.out'), $this->withClockGps())
            ->assertRedirect(route('employee.report', ['date' => now()->toDateString()]));

        $row = Attendance::where('user_id', $this->employee->id)->firstOrFail();
        $this->assertNotNull($row->clock_out);
    }

    public function test_clock_out_redirects_to_clock_when_report_already_filled(): void
    {
        $this->actingAs($this->employee)
            ->post(route('employee.clock.in'), $this->withClockGps(['shift_type' => Attendance::SHIFT_FULL_TIME]));

        Attendance::where('user_id', $this->employee->id)->firstOrFail()
            ->update(['daily_report' => 'Finished all tasks.']);

        $this->actingAs($this->employee)
            ->post(route('employee.clock.out'), $this->withClockGps())
            ->assertRedirect(route('employee.clock'))
            ->assertSessionHas('success');
    }

    public function test_clock_screen_shows_lateness_after_late_clock_in(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-05 10:25:00', config('app.timezone')));

        $this->actingAs($this->employee)
            ->post(route('employee.clock.in'), $this->withClockGps(['shift_type' => Attendance::SHIFT_FULL_TIME]))
            ->assertRedirect(route('employee.clock'));

        $this->actingAs($this->employee)
            ->get(route('employee.clock'))
            ->assertOk()
            ->assertSee(__('clock.should_start_at', [
                'shift' => __('clock.shifts.FULL_TIME'),
                'time' => '10:00',
            ]), false)
            ->assertSee(__('clock.late_by', [
                'minutes' => 25,
                'shift' => __('clock.shifts.FULL_TIME'),
            ]), false);

        Carbon::setTestNow();
    }

    public function test_second_open_clock_in_returns_validation_error(): void
    {
        $this->actingAs($this->employee)
            ->post(route('employee.clock.in'), $this->withClockGps(['shift_type' => Attendance::SHIFT_FULL_TIME]))
            ->assertRedirect();

        $this->actingAs($this->employee)
            ->from(route('employee.clock'))
            ->post(route('employee.clock.in'), $this->withClockGps(['shift_type' => Attendance::SHIFT_ONLINE_HALF]))
            ->assertRedirect(route('employee.clock'))
            ->assertSessionHasErrors('shift_type');

        $this->assertSame(1, Attendance::where('user_id', $this->employee->id)->count());
    }

    public function test_overtime_without_assignment_is_rejected(): void
    {
        $this->actingAs($this->employee)
            ->from(route('employee.clock'))
            ->post(route('employee.clock.in'), $this->withClockGps(['shift_type' => Attendance::SHIFT_OVERTIME]))
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
            ->post(route('employee.clock.in'), $this->withClockGps(['shift_type' => Attendance::SHIFT_OVERTIME]))
            ->assertRedirect(route('employee.clock'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('attendance', [
            'user_id' => $this->employee->id,
            'shift_type' => Attendance::SHIFT_OVERTIME,
        ]);
    }

    public function test_employee_cannot_clock_second_base_type_without_plan(): void
    {
        $this->actingAs($this->employee)
            ->post(route('employee.clock.in'), $this->withClockGps(['shift_type' => Attendance::SHIFT_FULL_TIME]))
            ->assertRedirect();

        $this->actingAs($this->employee)
            ->post(route('employee.clock.out'), $this->withClockGps())
            ->assertRedirect();

        $this->actingAs($this->employee)
            ->from(route('employee.clock'))
            ->post(route('employee.clock.in'), $this->withClockGps(['shift_type' => Attendance::SHIFT_ONLINE_HALF]))
            ->assertRedirect(route('employee.clock'))
            ->assertSessionHasErrors('shift_type');

        $this->assertSame(1, Attendance::where('user_id', $this->employee->id)->count());
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
            ->post(route('employee.clock.in'), $this->withClockGps(['shift_type' => Attendance::SHIFT_FULL_TIME]))
            ->assertRedirect(route('employee.clock'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('attendance', [
            'user_id' => $manager->id,
            'shift_type' => Attendance::SHIFT_FULL_TIME,
        ]);
    }

    public function test_tl_assignment_is_required_for_clock_type(): void
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
            ]), false);

        $html = $this->actingAs($this->employee)
            ->get(route('employee.clock'))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/option value="FULL_TIME"[^>]*selected/s',
            $html,
        );
        $this->assertStringNotContainsString('value="ONLINE_HALF"', $html);

        $this->actingAs($this->employee)
            ->post(route('employee.clock.in'), $this->withClockGps(['shift_type' => Attendance::SHIFT_ONLINE_HALF]))
            ->assertRedirect(route('employee.clock'))
            ->assertSessionHasErrors('shift_type');

        $this->actingAs($this->employee)
            ->post(route('employee.clock.in'), $this->withClockGps(['shift_type' => Attendance::SHIFT_FULL_TIME]))
            ->assertRedirect(route('employee.clock'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('attendance', [
            'user_id' => $this->employee->id,
            'shift_type' => Attendance::SHIFT_FULL_TIME,
        ]);
    }

    public function test_without_assignment_prefers_profile_default(): void
    {
        // Legacy ONLINE_FULL → both defaults; morning prefers Online½.
        $this->employee->update(['shift_type_default' => Attendance::SHIFT_ONLINE_FULL]);
        Carbon::setTestNow(Carbon::parse('2026-08-03 07:00:00', config('app.timezone')));

        $html = $this->actingAs($this->employee)
            ->get(route('employee.clock'))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/option value="ONLINE_HALF"[^>]*selected/s',
            $html,
        );
        $this->assertMatchesRegularExpression('/option value="FULL_TIME"/', $html);
        $this->assertStringNotContainsString(__('clock.assigned_today', [
            'shift' => __('clock.shifts.FULL_TIME'),
        ]), $html);
    }

    public function test_completed_full_time_is_hidden_and_rejected_same_day(): void
    {
        $this->actingAs($this->employee)
            ->post(route('employee.clock.in'), $this->withClockGps(['shift_type' => Attendance::SHIFT_FULL_TIME]))
            ->assertRedirect();
        $this->actingAs($this->employee)
            ->post(route('employee.clock.out'), $this->withClockGps())
            ->assertRedirect();

        $html = $this->actingAs($this->employee)
            ->get(route('employee.clock'))
            ->assertOk()
            ->getContent();

        $this->assertDoesNotMatchRegularExpression('/option value="FULL_TIME"/', $html);
        $this->assertDoesNotMatchRegularExpression('/option value="ONLINE_HALF"/', $html);
        $this->assertStringContainsString(__('clock.no_shifts_left'), $html);
        $this->assertStringNotContainsString(__('clock.preview_late', [
            'minutes' => 0,
            'shift' => __('clock.shifts.FULL_TIME'),
        ]), $html);
        $this->assertStringNotContainsString('ستكون متأخرًا', $html);
        $this->assertStringNotContainsString('will be late', $html);

        $this->actingAs($this->employee)
            ->from(route('employee.clock'))
            ->post(route('employee.clock.in'), $this->withClockGps(['shift_type' => Attendance::SHIFT_FULL_TIME]))
            ->assertRedirect(route('employee.clock'))
            ->assertSessionHasErrors('shift_type');
    }
}
