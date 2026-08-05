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
 * Gate 2.10 / T-2.5 — daily report after clock-out; one per day; locked after TL reply; XSS escaped.
 */
class DailyReportTest extends TestCase
{
    use RefreshDatabase;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->employee = User::factory()->employee()->create([
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
            'language_preference' => 'en',
        ]);
    }

    private function clockClosedShift(
        string $shift = Attendance::SHIFT_FULL_TIME,
        string $in = '2026-08-03 10:00:00',
        string $out = '2026-08-03 18:00:00',
    ): Attendance {
        $service = app(AttendanceService::class);
        $service->clockIn($this->employee, $shift, Carbon::parse($in));

        return $service->clockOut($this->employee, Carbon::parse($out));
    }

    public function test_employee_can_open_report_screen(): void
    {
        app()->setLocale('en');

        $this->actingAs($this->employee)
            ->get(route('employee.report'))
            ->assertOk()
            ->assertSee(__('report.title'), false)
            ->assertSee(__('report.today'), false)
            ->assertDontSee(__('report.load'), false);
    }

    public function test_report_requires_clock_out(): void
    {
        app(AttendanceService::class)->clockIn(
            $this->employee,
            Attendance::SHIFT_FULL_TIME,
            Carbon::parse('2026-08-03 10:00:00'),
        );

        $this->actingAs($this->employee)
            ->from(route('employee.report', ['date' => '2026-08-03']))
            ->post(route('employee.report.store'), [
                'date' => '2026-08-03',
                'daily_report' => 'Still working',
            ])
            ->assertRedirect(route('employee.report', ['date' => '2026-08-03']))
            ->assertSessionHasErrors('daily_report');
    }

    public function test_employee_can_submit_report_after_clock_out(): void
    {
        $this->clockClosedShift();

        $this->actingAs($this->employee)
            ->post(route('employee.report.store'), [
                'date' => '2026-08-03',
                'daily_report' => 'Finished all tasks for today.',
            ])
            ->assertRedirect(route('employee.report', ['date' => '2026-08-03']))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('attendance', [
            'user_id' => $this->employee->id,
            'daily_report' => 'Finished all tasks for today.',
        ]);
    }

    public function test_one_report_per_day_uses_first_shift_row(): void
    {
        $leader = User::factory()->teamLeader()->create();
        $this->employee->update(['team_leader_id' => $leader->id]);

        // Phase 6: FT ↔ Online only when both are on the day's plan.
        ShiftAssignment::factory()->fullTime()->create([
            'user_id' => $this->employee->id,
            'assigned_by_tl_id' => $leader->id,
            'date' => '2026-08-03',
        ]);
        ShiftAssignment::factory()->onlineHalf()->create([
            'user_id' => $this->employee->id,
            'assigned_by_tl_id' => $leader->id,
            'date' => '2026-08-03',
            'expected_start_time' => '15:00',
            'expected_end_time' => '19:00',
            'expected_hours' => 4,
        ]);

        $first = $this->clockClosedShift(
            Attendance::SHIFT_FULL_TIME,
            '2026-08-03 10:00:00',
            '2026-08-03 14:00:00',
        );
        $second = $this->clockClosedShift(
            Attendance::SHIFT_ONLINE_HALF,
            '2026-08-03 15:00:00',
            '2026-08-03 19:00:00',
        );

        $this->actingAs($this->employee)
            ->post(route('employee.report.store'), [
                'date' => '2026-08-03',
                'daily_report' => 'Day summary on first shift.',
            ])
            ->assertRedirect();

        $this->assertSame('Day summary on first shift.', $first->fresh()->daily_report);
        $this->assertNull($second->fresh()->daily_report);
    }

    public function test_report_is_locked_after_tl_response(): void
    {
        $row = $this->clockClosedShift();
        $row->update([
            'daily_report' => 'Original report',
            'team_leader_response' => 'Well done',
        ]);

        $this->actingAs($this->employee)
            ->from(route('employee.report', ['date' => '2026-08-03']))
            ->post(route('employee.report.store'), [
                'date' => '2026-08-03',
                'daily_report' => 'Trying to change',
            ])
            ->assertRedirect(route('employee.report', ['date' => '2026-08-03']))
            ->assertSessionHasErrors('daily_report');

        $this->assertSame('Original report', $row->fresh()->daily_report);
    }

    public function test_tl_response_is_escaped_in_blade(): void
    {
        $row = $this->clockClosedShift();
        $row->update([
            'daily_report' => 'My work',
            'team_leader_response' => '<script>alert(1)</script>Nice',
        ]);

        $this->actingAs($this->employee)
            ->get(route('employee.report', ['date' => '2026-08-03']))
            ->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;Nice', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_team_leader_can_access_own_report_screen(): void
    {
        $this->actingAs(User::factory()->teamLeader()->create([
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
        ]))
            ->get(route('employee.report'))
            ->assertOk();
    }
}
