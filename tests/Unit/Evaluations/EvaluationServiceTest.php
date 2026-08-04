<?php

namespace Tests\Unit\Evaluations;

use App\Models\Attendance;
use App\Models\Evaluation;
use App\Models\User;
use App\Services\Evaluations\EvaluationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Gate 3.2–3.3 / T-3.2 — monthlyFromDaily (/5) + legacy four-metric 0.97375.
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
        $employee = User::factory()->employee()->create();

        // Day 1
        Attendance::factory()->create([
            'user_id' => $employee->id,
            'date' => '2026-08-03',
            'shift_type' => Attendance::SHIFT_FULL_TIME,
            'performance_score' => 100,
            'behavior_score' => 80,
            'productivity_score' => 90,
            'interest_return_score' => 70,
            'attendance_score' => 100,
        ]);

        // Day 2
        Attendance::factory()->create([
            'user_id' => $employee->id,
            'date' => '2026-08-04',
            'shift_type' => Attendance::SHIFT_FULL_TIME,
            'performance_score' => 80,
            'behavior_score' => 100,
            'productivity_score' => 70,
            'interest_return_score' => 90,
            'attendance_score' => 50,
        ]);

        // Other month ignored
        Attendance::factory()->create([
            'user_id' => $employee->id,
            'date' => '2026-07-15',
            'shift_type' => Attendance::SHIFT_FULL_TIME,
            'performance_score' => 10,
            'behavior_score' => 10,
            'productivity_score' => 10,
            'interest_return_score' => 10,
            'attendance_score' => 10,
        ]);

        $result = $this->service->monthlyFromDaily($employee, 8, 2026);

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
        $employee = User::factory()->employee()->create();

        // Four days → monthly avgs 98, 97, 97.5, 97 → legacy 0.97375
        foreach ([
            ['2026-08-01', 100, 100, 100, 100],
            ['2026-08-02', 100, 100, 95, 100],
            ['2026-08-03', 96, 94, 95, 94],
            ['2026-08-04', 96, 94, 100, 94],
        ] as [$date, $p, $b, $pr, $a]) {
            Attendance::factory()->create([
                'user_id' => $employee->id,
                'date' => $date,
                'shift_type' => Attendance::SHIFT_FULL_TIME,
                'performance_score' => $p,
                'behavior_score' => $b,
                'productivity_score' => $pr,
                'interest_return_score' => 50, // excluded from legacy four
                'attendance_score' => $a,
            ]);
        }

        $result = $this->service->monthlyFromDaily($employee, 8, 2026);

        $this->assertSame(98.0, $result['performance_score']);
        $this->assertSame(97.0, $result['behavior_score']);
        $this->assertSame(97.5, $result['productivity_score']);
        $this->assertSame(97.0, $result['attendance_score']);
        $this->assertSame(0.97375, $result['legacy_four_average_0_1']);
        // App total includes interest_return avg 50: (98+97+97.5+50+97)/5 = 87.9
        $this->assertSame(87.9, $result['total_score']);
    }

    public function test_save_monthly_persists_averages_and_text(): void
    {
        $leader = User::factory()->teamLeader()->create();
        $employee = User::factory()->employee()->create([
            'team_leader_id' => $leader->id,
        ]);

        Attendance::factory()->create([
            'user_id' => $employee->id,
            'date' => '2026-08-05',
            'shift_type' => Attendance::SHIFT_FULL_TIME,
            'performance_score' => 90,
            'behavior_score' => 80,
            'productivity_score' => 85,
            'interest_return_score' => 75,
            'attendance_score' => 100,
        ]);

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
            'month' => 8,
            'year' => 2026,
        ]);
    }

    public function test_summary_for_payslip_includes_year_average_of_monthly_totals(): void
    {
        $leader = User::factory()->teamLeader()->create();
        $employee = User::factory()->employee()->create(['team_leader_id' => $leader->id]);

        Attendance::factory()->create([
            'user_id' => $employee->id,
            'date' => '2026-05-01',
            'performance_score' => 100,
            'behavior_score' => 100,
            'productivity_score' => 100,
            'interest_return_score' => 100,
            'attendance_score' => 100,
        ]);

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
}
