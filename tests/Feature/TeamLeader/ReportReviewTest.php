<?php

namespace Tests\Feature\TeamLeader;

use App\Models\Attendance;
use App\Models\User;
use App\Services\Attendance\AttendanceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Gate 2.10–2.11 / T-2.6 — TL report queue + reply; team-scoped; XSS escaped; employee locks.
 */
class ReportReviewTest extends TestCase
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
            'language_preference' => 'en',
            'name_en' => 'Team Member One',
        ]);
        $otherLeader = User::factory()->teamLeader()->create();
        $this->otherEmployee = User::factory()->employee()->create([
            'team_leader_id' => $otherLeader->id,
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
            'name_en' => 'Other Team Person',
        ]);
    }

    private function closedShiftWithReport(User $user, string $report = 'Finished tasks.'): Attendance
    {
        $service = app(AttendanceService::class);
        $service->clockIn($user, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-08-03 10:00:00'));
        $row = $service->clockOut($user, Carbon::parse('2026-08-03 18:00:00'));
        $row->update(['daily_report' => $report]);

        return $row->fresh();
    }

    public function test_team_leader_sees_pending_team_reports_only(): void
    {
        app()->setLocale('en');

        $own = $this->closedShiftWithReport($this->employee, 'My day report');
        $this->closedShiftWithReport($this->otherEmployee, 'Secret other team');

        $this->actingAs($this->leader)
            ->get(route('team-leader.reports'))
            ->assertOk()
            ->assertSee(__('report.tl.title'), false)
            ->assertSee(__('report.tl.all_employees'), false)
            ->assertSee('Team Member One', false)
            ->assertSee('My day report', false)
            ->assertDontSee('Other Team Person', false)
            ->assertDontSee('Secret other team', false)
            ->assertSee(route('team-leader.reports.show', $own), false);
    }

    public function test_team_leader_can_filter_by_employee(): void
    {
        app()->setLocale('en');

        $second = User::factory()->employee()->create([
            'team_leader_id' => $this->leader->id,
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
            'name_en' => 'Second Teammate',
        ]);

        $this->closedShiftWithReport($this->employee, 'Report from one');
        $service = app(AttendanceService::class);
        $service->clockIn($second, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-08-04 10:00:00'));
        $row2 = $service->clockOut($second, Carbon::parse('2026-08-04 18:00:00'));
        $row2->update(['daily_report' => 'Report from two']);

        $this->actingAs($this->leader)
            ->get(route('team-leader.reports', ['employee_id' => $second->id, 'filter' => 'all']))
            ->assertOk()
            ->assertSee('Second Teammate', false)
            ->assertSee('Report from two', false)
            ->assertDontSee('Report from one', false);
    }

    public function test_all_filter_includes_replied_reports(): void
    {
        $row = $this->closedShiftWithReport($this->employee, 'Already answered');
        $row->update(['team_leader_response' => 'Thanks']);

        $this->actingAs($this->leader)
            ->get(route('team-leader.reports', ['filter' => 'all']))
            ->assertOk()
            ->assertSee('Already answered', false);

        $this->actingAs($this->leader)
            ->get(route('team-leader.reports', ['filter' => 'pending']))
            ->assertOk()
            ->assertDontSee('Already answered', false);
    }

    public function test_team_leader_can_reply_and_employee_report_locks(): void
    {
        $row = $this->closedShiftWithReport($this->employee);

        $this->actingAs($this->leader)
            ->post(route('team-leader.reports.respond', $row), [
                'team_leader_response' => 'Great work today.',
            ])
            ->assertRedirect(route('team-leader.reports.show', $row))
            ->assertSessionHas('success');

        $this->assertSame('Great work today.', $row->fresh()->team_leader_response);

        $this->actingAs($this->employee)
            ->from(route('employee.report', ['date' => '2026-08-03']))
            ->post(route('employee.report.store'), [
                'date' => '2026-08-03',
                'daily_report' => 'Trying to edit after lock',
            ])
            ->assertRedirect(route('employee.report', ['date' => '2026-08-03']))
            ->assertSessionHasErrors('daily_report');

        $this->actingAs($this->employee)
            ->get(route('employee.report', ['date' => '2026-08-03']))
            ->assertOk()
            ->assertSee('Great work today.', false);
    }

    public function test_team_leader_cannot_open_other_team_report(): void
    {
        $other = $this->closedShiftWithReport($this->otherEmployee);

        $this->actingAs($this->leader)
            ->get(route('team-leader.reports.show', $other))
            ->assertForbidden();

        $this->actingAs($this->leader)
            ->post(route('team-leader.reports.respond', $other), [
                'team_leader_response' => 'Nope',
            ])
            ->assertForbidden();
    }

    public function test_employee_report_and_tl_reply_are_escaped(): void
    {
        $row = $this->closedShiftWithReport($this->employee, '<script>alert(1)</script>Work');

        $this->actingAs($this->leader)
            ->get(route('team-leader.reports.show', $row))
            ->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;Work', false)
            ->assertDontSee('<script>alert(1)</script>', false);

        $this->actingAs($this->leader)
            ->post(route('team-leader.reports.respond', $row), [
                'team_leader_response' => '<b>ok</b>',
            ])
            ->assertRedirect();

        $this->actingAs($this->employee)
            ->get(route('employee.report', ['date' => '2026-08-03']))
            ->assertOk()
            ->assertSee('&lt;b&gt;ok&lt;/b&gt;', false)
            ->assertDontSee('<b>ok</b>', false);
    }

    public function test_validation_requires_response_text(): void
    {
        $row = $this->closedShiftWithReport($this->employee);

        $this->actingAs($this->leader)
            ->from(route('team-leader.reports.show', $row))
            ->post(route('team-leader.reports.respond', $row), [
                'team_leader_response' => '',
            ])
            ->assertRedirect(route('team-leader.reports.show', $row))
            ->assertSessionHasErrors('team_leader_response');
    }

    public function test_employee_cannot_access_tl_reports_queue(): void
    {
        $this->actingAs($this->employee)
            ->get(route('team-leader.reports'))
            ->assertForbidden();
    }

    public function test_manager_can_reply_to_any_employee_report(): void
    {
        $row = $this->closedShiftWithReport($this->otherEmployee);
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)
            ->post(route('team-leader.reports.respond', $row), [
                'team_leader_response' => 'Manager reply',
            ])
            ->assertRedirect(route('team-leader.reports.show', $row));

        $this->assertSame('Manager reply', $row->fresh()->team_leader_response);
    }
}
