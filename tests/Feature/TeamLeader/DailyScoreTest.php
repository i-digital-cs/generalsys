<?php

namespace Tests\Feature\TeamLeader;

use App\Models\Attendance;
use App\Models\AttendanceDailyScore;
use App\Models\User;
use App\Services\Attendance\AttendanceService;
use App\Services\Employees\TeamMembershipService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Gate 3.4 / T-3.1 / T-8.5 — per-TL daily scores on report review; attendance_score is auto.
 */
class DailyScoreTest extends TestCase
{
    use RefreshDatabase;

    private User $leader;

    private User $employee;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->leader = User::factory()->teamLeader()->create([
            'name_en' => 'Leader One',
            'language_preference' => 'en',
        ]);
        $this->employee = User::factory()->employee()->create([
            'team_leader_id' => $this->leader->id,
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
            'language_preference' => 'en',
        ]);
        $this->manager = User::factory()->manager()->create([
            'language_preference' => 'en',
        ]);
    }

    private function closedShiftWithReport(): Attendance
    {
        $service = app(AttendanceService::class);
        $service->clockIn($this->employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-08-03 10:00:00'));
        $row = $service->clockOut($this->employee, Carbon::parse('2026-08-03 18:00:00'));
        $row->update(['daily_report' => 'Finished tasks.']);

        return $row->fresh();
    }

    public function test_team_leader_sees_score_form_with_auto_attendance(): void
    {
        app()->setLocale('en');
        $row = $this->closedShiftWithReport();

        $this->actingAs($this->leader)
            ->get(route('team-leader.reports.show', $row))
            ->assertOk()
            ->assertSee(__('evaluation.scores_title'), false)
            ->assertSee(__('evaluation.fields.performance'), false)
            ->assertSee(__('evaluation.fields.attendance'), false)
            ->assertSee('100.00', false);
    }

    public function test_team_leader_can_save_four_daily_scores(): void
    {
        $row = $this->closedShiftWithReport();

        $this->actingAs($this->leader)
            ->put(route('team-leader.reports.scores', $row), [
                'performance_score' => 90,
                'behavior_score' => 85.5,
                'productivity_score' => 88,
                'interest_return_score' => 92,
            ])
            ->assertRedirect(route('team-leader.reports.show', $row))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('attendance_daily_scores', [
            'attendance_id' => $row->id,
            'team_leader_id' => $this->leader->id,
            'scored_by' => $this->leader->id,
            'performance_score' => 90.00,
            'behavior_score' => 85.50,
            'productivity_score' => 88.00,
            'interest_return_score' => 92.00,
        ]);
        $this->assertSame('100.00', (string) $row->fresh()->attendance_score);
        $this->assertFalse(Schema::hasColumn('attendance', 'performance_score'));
    }

    public function test_two_team_leaders_save_separate_daily_scores(): void
    {
        app()->setLocale('en');
        $leaderB = User::factory()->teamLeader()->create([
            'name_en' => 'Leader Two',
            'language_preference' => 'en',
        ]);
        app(TeamMembershipService::class)->syncLeaders($this->employee, [
            $this->leader->id,
            $leaderB->id,
        ]);

        $row = $this->closedShiftWithReport();

        $this->actingAs($this->leader)
            ->put(route('team-leader.reports.scores', $row), [
                'performance_score' => 90,
                'behavior_score' => 90,
                'productivity_score' => 90,
                'interest_return_score' => 90,
            ])
            ->assertRedirect();

        $this->actingAs($leaderB)
            ->put(route('team-leader.reports.scores', $row), [
                'performance_score' => 70,
                'behavior_score' => 70,
                'productivity_score' => 70,
                'interest_return_score' => 70,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('attendance_daily_scores', [
            'attendance_id' => $row->id,
            'team_leader_id' => $this->leader->id,
            'performance_score' => 90.00,
        ]);
        $this->assertDatabaseHas('attendance_daily_scores', [
            'attendance_id' => $row->id,
            'team_leader_id' => $leaderB->id,
            'performance_score' => 70.00,
        ]);
        $this->assertSame(2, AttendanceDailyScore::query()->where('attendance_id', $row->id)->count());

        $this->actingAs($this->leader)
            ->get(route('team-leader.reports.show', $row))
            ->assertOk()
            ->assertSee('Leader Two', false)
            ->assertSee('70.00', false)
            ->assertSee('90.00', false);
    }

    public function test_validation_rejects_score_over_100(): void
    {
        $row = $this->closedShiftWithReport();

        $this->actingAs($this->leader)
            ->put(route('team-leader.reports.scores', $row), [
                'performance_score' => 101,
                'behavior_score' => 80,
                'productivity_score' => 80,
                'interest_return_score' => 80,
            ])
            ->assertSessionHasErrors('performance_score');
    }

    public function test_team_leader_cannot_score_other_team(): void
    {
        $otherLeader = User::factory()->teamLeader()->create();
        $other = User::factory()->employee()->create([
            'team_leader_id' => $otherLeader->id,
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
        ]);

        $service = app(AttendanceService::class);
        $service->clockIn($other, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-08-03 10:00:00'));
        $row = $service->clockOut($other, Carbon::parse('2026-08-03 18:00:00'));
        $row->update(['daily_report' => 'Other team']);

        $this->actingAs($this->leader)
            ->put(route('team-leader.reports.scores', $row->fresh()), [
                'performance_score' => 50,
                'behavior_score' => 50,
                'productivity_score' => 50,
                'interest_return_score' => 50,
            ])
            ->assertForbidden();
    }

    public function test_manager_can_score_any_employee(): void
    {
        $row = $this->closedShiftWithReport();

        $this->actingAs($this->manager)
            ->put(route('team-leader.reports.scores', $row), [
                'performance_score' => 70,
                'behavior_score' => 71,
                'productivity_score' => 72,
                'interest_return_score' => 73,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('attendance_daily_scores', [
            'attendance_id' => $row->id,
            'team_leader_id' => $this->manager->id,
            'scored_by' => $this->manager->id,
            'performance_score' => 70.00,
        ]);
    }

    public function test_employee_cannot_score(): void
    {
        $row = $this->closedShiftWithReport();

        $this->actingAs($this->employee)
            ->put(route('team-leader.reports.scores', $row), [
                'performance_score' => 100,
                'behavior_score' => 100,
                'productivity_score' => 100,
                'interest_return_score' => 100,
            ])
            ->assertForbidden();
    }
}
