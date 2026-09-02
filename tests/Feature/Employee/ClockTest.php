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

        $this->freezeClockToMorningShift();
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
            ->assertSee(__('clock.shift_window', [
                'shift' => __('clock.shifts.FULL_TIME'),
            ]), false)
            ->assertSee(__('clock.flexible_start_hint'), false)
            ->assertDontSee('value="ONLINE_HALF"', false)
            ->assertDontSee('value="ONLINE_FULL"', false)
            ->assertDontSee('value="OVERTIME"', false)
            ->assertDontSee('value="EXTRA_SHIFT"', false)
            ->assertSee(__('clock.rotation_hint'), false)
            ->assertSee('clock-cta--green', false);
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
            ->assertSee(__('clock.late_minutes'), false)
            ->assertSee('clock-cta--orange', false);

        // Missing daily report → routed to the report page with a reminder banner.
        $this->actingAs($this->employee)
            ->followingRedirects()
            ->post(route('employee.clock.out'), $this->withClockGps())
            ->assertOk()
            ->assertSee(__('report.reminder.title', [], 'ar'), false)
            ->assertSee('report-reminder-banner', false);

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

    public function test_clock_screen_shows_flexible_window_without_auto_lateness(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-05 10:25:00', config('app.timezone')));

        $this->actingAs($this->employee)
            ->post(route('employee.clock.in'), $this->withClockGps(['shift_type' => Attendance::SHIFT_FULL_TIME]))
            ->assertRedirect(route('employee.clock'));

        $this->actingAs($this->employee)
            ->get(route('employee.clock'))
            ->assertOk()
            ->assertSee(__('clock.shift_window', [
                'shift' => __('clock.shifts.FULL_TIME'),
            ]), false)
            ->assertSee(__('clock.flexible_start_hint'), false)
            ->assertDontSee(__('clock.late_by', [
                'minutes' => 25,
                'shift' => __('clock.shifts.FULL_TIME'),
            ]), false);

        Carbon::setTestNow();
    }

    public function test_clock_out_report_reminder_shows_when_report_missing(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-05 10:25:00', config('app.timezone')));

        $this->actingAs($this->employee)
            ->post(route('employee.clock.in'), $this->withClockGps(['shift_type' => Attendance::SHIFT_FULL_TIME]))
            ->assertRedirect();

        Carbon::setTestNow(Carbon::parse('2026-08-05 18:00:00', config('app.timezone')));

        $this->actingAs($this->employee)
            ->followingRedirects()
            ->post(route('employee.clock.out'), $this->withClockGps())
            ->assertOk()
            ->assertSee('report-reminder-banner', false)
            ->assertSee(__('report.reminder.on_time'), false);

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
        Carbon::setTestNow(Carbon::parse('2026-08-03 18:05:00', config('app.timezone')));

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
        $this->employee->update([
            'shift_type_default' => Attendance::SHIFT_ONLINE_FULL,
            'shift_types_default' => [
                ['type' => Attendance::SHIFT_ONLINE_FULL, 'start' => '10:00', 'end' => '22:00'],
            ],
            'work_start_time' => '10:00',
            'work_end_time' => '22:00',
        ]);
        Carbon::setTestNow(Carbon::parse('2026-08-03 10:15:00', config('app.timezone')));

        $html = $this->actingAs($this->employee)
            ->get(route('employee.clock'))
            ->assertOk()
            ->assertSee(__('clock.shifts.ONLINE_FULL'), false)
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/option value="ONLINE_FULL"[^>]*selected/s',
            $html,
        );
        $this->assertDoesNotMatchRegularExpression('/option value="FULL_TIME"/', $html);
        $this->assertDoesNotMatchRegularExpression('/option value="ONLINE_HALF"/', $html);
    }

    public function test_completed_full_time_can_resume_same_day(): void
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

        $this->assertMatchesRegularExpression('/option value="FULL_TIME"/', $html);
        $this->assertStringContainsString(__('clock.resume_hint'), $html);
        $this->assertStringContainsString(__('clock.today_segments'), $html);

        $this->actingAs($this->employee)
            ->from(route('employee.clock'))
            ->post(route('employee.clock.in'), $this->withClockGps(['shift_type' => Attendance::SHIFT_FULL_TIME]))
            ->assertRedirect(route('employee.clock'))
            ->assertSessionHas('success');
    }

    public function test_clock_still_offers_missed_full_time_after_evening_online(): void
    {
        $this->employee->update([
            'shift_types_default' => [
                ['type' => Attendance::SHIFT_FULL_TIME, 'start' => '10:00', 'end' => '18:00'],
                ['type' => Attendance::SHIFT_ONLINE_HALF, 'start' => '18:00', 'end' => '22:00'],
            ],
            'shift_type_default' => Attendance::SHIFT_FULL_TIME,
        ]);

        Carbon::setTestNow(Carbon::parse('2026-08-03 18:05:00', config('app.timezone')));
        $this->actingAs($this->employee)
            ->post(route('employee.clock.in'), ['shift_type' => Attendance::SHIFT_ONLINE_HALF])
            ->assertRedirect();
        $this->actingAs($this->employee)
            ->post(route('employee.clock.out'), [])
            ->assertRedirect();

        Carbon::setTestNow(Carbon::parse('2026-08-03 20:35:00', config('app.timezone')));

        $this->actingAs($this->employee)
            ->get(route('employee.clock'))
            ->assertOk()
            ->assertSee('value="FULL_TIME"', false)
            ->assertDontSee(__('clock.no_shifts_left'), false);

        Carbon::setTestNow();
    }

    public function test_evening_online_stays_open_after_full_time_ends(): void
    {
        $this->employee->update([
            'shift_types_default' => [
                ['type' => Attendance::SHIFT_FULL_TIME, 'start' => '10:00', 'end' => '18:00'],
                ['type' => Attendance::SHIFT_ONLINE_HALF, 'start' => '18:00', 'end' => '22:00'],
            ],
            'shift_type_default' => Attendance::SHIFT_FULL_TIME,
        ]);

        Carbon::setTestNow(Carbon::parse('2026-08-03 20:35:00', config('app.timezone')));

        $this->actingAs($this->employee)
            ->get(route('employee.clock'))
            ->assertOk()
            ->assertSee(__('clock.clock_in'), false)
            ->assertSee('value="ONLINE_HALF"', false)
            ->assertSee('value="FULL_TIME"', false);

        $this->actingAs($this->employee)
            ->post(route('employee.clock.in'), ['shift_type' => Attendance::SHIFT_ONLINE_HALF])
            ->assertRedirect(route('employee.clock'))
            ->assertSessionHas('success');

        Carbon::setTestNow();
    }
}
