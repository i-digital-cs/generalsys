<?php

namespace Tests\Unit\Evaluations;

use App\Models\Attendance;
use App\Models\AttendanceDailyScore;
use App\Models\Evaluation;
use App\Models\User;
use App\Services\Employees\TeamMembershipService;
use App\Services\Evaluations\EvaluationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Gate 3.2–3.3 / T-3.2 / T-8.5 — monthlyFromDaily (/5) + legacy four-metric 0.97375.
 */
class EvaluationServiceTest extends TestCase
{
    use RefreshDatabase;

    private EvaluationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EvaluationService::class);
    }

    public function test_legacy_four_metric_fixture_is_0_97375(): void
    {
        // 0–100 scores whose /4/100 = Excel J10 fixture 0.97375
        $fraction = $this->service->legacyFourAverageAsFraction(
            98.0,
            97.0,
            97.5,
            97.0,
        );

        $this->assertSame(0.97375, $fraction);
    }

    public function test_total_from_five_is_equal_average(): void
    {
        $total = $this->service->totalFromFive(100, 80, 90, 70, 60);

        $this->assertSame(80.0, $total);
    }

    public function test_monthly_from_daily_averages_five_metrics(): void
    {
        $leader = User::factory()->teamLeader()->create();
        $employee = User::factory()->employee()->create(['team_leader_id' => $leader->id]);

        $this->scoreDay($employee, $leader, '2026-08-03', 100, 80, 90, 70, 100);
        $this->scoreDay($employee, $leader, '2026-08-04', 80, 100, 70, 90, 50);
        $this->scoreDay($employee, $leader, '2026-07-15', 10, 10, 10, 10, 10);

        $result = $this->service->monthlyFromDaily($employee, 8, 2026, $leader->id);

        $this->assertSame(90.0, $result['performance_score']);
        $this->assertSame(90.0, $result['behavior_score']);
        $this->assertSame(80.0, $result['productivity_score']);
        $this->assertSame(80.0, $result['interest_return_score']);
        $this->assertSame(75.0, $result['attendance_score']);
        // (90+90+80+80+75)/5 = 83
        $this->assertSame(415.0, $result['scores_sum']);
        $this->assertSame(83.0, $result['total_score']);
    }

    public function test_monthly_from_daily_legacy_four_matches_fixture_rows(): void
    {
        $leader = User::factory()->teamLeader()->create();
        $employee = User::factory()->employee()->create(['team_leader_id' => $leader->id]);

        // Four days → monthly avgs 98, 97, 97.5, 97 → legacy 0.97375
        foreach ([
            ['2026-08-01', 100, 100, 100, 100],
            ['2026-08-02', 100, 100, 95, 100],
            ['2026-08-03', 96, 94, 95, 94],
            ['2026-08-04', 96, 94, 100, 94],
        ] as [$date, $p, $b, $pr, $a]) {
            $this->scoreDay($employee, $leader, $date, $p, $b, $pr, 50, $a);
        }

        $result = $this->service->monthlyFromDaily($employee, 8, 2026, $leader->id);

        $this->assertSame(98.0, $result['performance_score']);
        $this->assertSame(97.0, $result['behavior_score']);
        $this->assertSame(97.5, $result['productivity_score']);
        $this->assertSame(97.0, $result['attendance_score']);
        $this->assertSame(0.97375, $result['legacy_four_average_0_1']);
        // App total includes interest_return avg 50: (98+97+97.5+50+97)/5 = 87.9
        $this->assertSame(87.9, $result['total_score']);
    }

    public function test_monthly_from_daily_keeps_each_tl_scores_separate(): void
    {
        $leaderA = User::factory()->teamLeader()->create();
        $leaderB = User::factory()->teamLeader()->create();
        $employee = User::factory()->employee()->create(['team_leader_id' => $leaderA->id]);
        app(TeamMembershipService::class)
            ->syncLeaders($employee, [$leaderA->id, $leaderB->id]);

        $day = Attendance::factory()->create([
            'user_id' => $employee->id,
            'date' => '2026-08-03',
            'shift_type' => Attendance::SHIFT_FULL_TIME,
            'attendance_score' => 100,
        ]);

        AttendanceDailyScore::factory()->create([
            'attendance_id' => $day->id,
            'team_leader_id' => $leaderA->id,
            'scored_by' => $leaderA->id,
            'performance_score' => 90,
            'behavior_score' => 90,
            'productivity_score' => 90,
            'interest_return_score' => 90,
        ]);
        AttendanceDailyScore::factory()->create([
            'attendance_id' => $day->id,
            'team_leader_id' => $leaderB->id,
            'scored_by' => $leaderB->id,
            'performance_score' => 70,
            'behavior_score' => 70,
            'productivity_score' => 70,
            'interest_return_score' => 70,
        ]);

        $fromA = $this->service->monthlyFromDaily($employee, 8, 2026, $leaderA->id);
        $fromB = $this->service->monthlyFromDaily($employee, 8, 2026, $leaderB->id);
        $combined = $this->service->monthlyFromDaily($employee, 8, 2026);

        $this->assertSame(90.0, $fromA['performance_score']);
        $this->assertSame(70.0, $fromB['performance_score']);
        $this->assertSame(80.0, $combined['performance_score']);
    }

    public function test_save_monthly_persists_averages_and_text(): void
    {
        $leader = User::factory()->teamLeader()->create();
        $employee = User::factory()->employee()->create([
            'team_leader_id' => $leader->id,
        ]);

        $this->scoreDay($employee, $leader, '2026-08-05', 90, 80, 85, 75, 100);

        $evaluation = $this->service->saveMonthly(
            $leader,
            $employee,
            8,
            2026,
            'Strong month overall.',
        );

        $this->assertSame($employee->id, $evaluation->user_id);
        $this->assertSame($leader->id, $evaluation->team_leader_id);
        $this->assertSame('Strong month overall.', $evaluation->evaluation_text);
        $this->assertSame('90.00', (string) $evaluation->performance_score);
        $this->assertSame('86.00', (string) $evaluation->total_score); // (90+80+85+75+100)/5
        $this->assertDatabaseHas('evaluations', [
            'user_id' => $employee->id,
            'team_leader_id' => $leader->id,
            'month' => 8,
            'year' => 2026,
        ]);
    }

    public function test_summary_for_payslip_includes_year_average_of_monthly_totals(): void
    {
        $leader = User::factory()->teamLeader()->create();
        $employee = User::factory()->employee()->create(['team_leader_id' => $leader->id]);

        $this->scoreDay($employee, $leader, '2026-05-01', 100, 100, 100, 100, 100);

        $this->service->saveMonthly($leader, $employee, 2, 2026, null);
        Evaluation::query()->where('user_id', $employee->id)->where('month', 2)->update([
            'total_score' => 96.00,
            'performance_score' => 96,
            'behavior_score' => 96,
            'productivity_score' => 96,
            'interest_return_score' => 96,
            'attendance_score' => 96,
        ]);

        $summary = $this->service->summaryForPayslip($employee, 5, 2026);

        $this->assertSame(100.0, $summary['scores']['total_score']);
        $this->assertSame(96.0, $summary['monthly_totals'][2]);
        $this->assertSame(100.0, $summary['monthly_totals'][5]);
        // year = (96 + 100) / 2
        $this->assertSame(98.0, $summary['year_average']);
    }

    public function test_summary_for_payslip_averages_two_tl_totals_for_the_month(): void
    {
        $leaderA = User::factory()->teamLeader()->create(['name_en' => 'Alpha Lead']);
        $leaderB = User::factory()->teamLeader()->create(['name_en' => 'Beta Lead']);
        $employee = User::factory()->employee()->create(['team_leader_id' => $leaderA->id]);
        app(TeamMembershipService::class)
            ->syncLeaders($employee, [$leaderA->id, $leaderB->id]);

        Evaluation::factory()->create([
            'user_id' => $employee->id,
            'team_leader_id' => $leaderA->id,
            'month' => 8,
            'year' => 2026,
            'performance_score' => 80,
            'behavior_score' => 80,
            'productivity_score' => 80,
            'interest_return_score' => 80,
            'attendance_score' => 80,
            'total_score' => 80,
            'evaluation_text' => 'From A',
        ]);
        Evaluation::factory()->create([
            'user_id' => $employee->id,
            'team_leader_id' => $leaderB->id,
            'month' => 8,
            'year' => 2026,
            'performance_score' => 100,
            'behavior_score' => 100,
            'productivity_score' => 100,
            'interest_return_score' => 100,
            'attendance_score' => 100,
            'total_score' => 100,
            'evaluation_text' => 'From B',
        ]);

        $summary = $this->service->summaryForPayslip($employee, 8, 2026);

        $this->assertSame(90.0, $summary['scores']['total_score']);
        $this->assertSame(90.0, $summary['monthly_totals'][8]);
        $this->assertTrue($summary['is_saved']);
        $this->assertStringContainsString('From A', (string) $summary['evaluation_text']);
        $this->assertStringContainsString('From B', (string) $summary['evaluation_text']);
    }

    private function scoreDay(
        User $employee,
        User $leader,
        string $date,
        float $performance,
        float $behavior,
        float $productivity,
        float $interestReturn,
        float $attendance,
    ): void {
        $row = Attendance::factory()->create([
            'user_id' => $employee->id,
            'date' => $date,
            'shift_type' => Attendance::SHIFT_FULL_TIME,
            'attendance_score' => $attendance,
        ]);

        AttendanceDailyScore::factory()->create([
            'attendance_id' => $row->id,
            'team_leader_id' => $leader->id,
            'scored_by' => $leader->id,
            'performance_score' => $performance,
            'behavior_score' => $behavior,
            'productivity_score' => $productivity,
            'interest_return_score' => $interestReturn,
        ]);
    }
}
