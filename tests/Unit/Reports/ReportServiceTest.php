<?php

namespace Tests\Unit\Reports;

use App\Models\Evaluation;
use App\Models\User;
use App\Services\Reports\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Gate 5.1 — year evaluation average (L33). */
class ReportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_annual_evaluation_is_average_of_monthly_totals(): void
    {
        $leader = User::factory()->teamLeader()->create();
        $employee = User::factory()->employee()->create();

        Evaluation::factory()->create([
            'user_id' => $employee->id,
            'team_leader_id' => $leader->id,
            'month' => 1,
            'year' => 2026,
            'total_score' => 90,
        ]);
        Evaluation::factory()->create([
            'user_id' => $employee->id,
            'team_leader_id' => $leader->id,
            'month' => 2,
            'year' => 2026,
            'total_score' => 80,
        ]);
        Evaluation::factory()->create([
            'user_id' => $employee->id,
            'team_leader_id' => $leader->id,
            'month' => 3,
            'year' => 2026,
            'total_score' => 100,
        ]);
        // Other year ignored
        Evaluation::factory()->create([
            'user_id' => $employee->id,
            'team_leader_id' => $leader->id,
            'month' => 1,
            'year' => 2025,
            'total_score' => 50,
        ]);

        $avg = app(ReportService::class)->annualEvaluation($employee, 2026);

        $this->assertSame(90.0, $avg);
    }

    public function test_annual_evaluation_averages_per_month_then_year_when_two_tls(): void
    {
        $leaderA = User::factory()->teamLeader()->create();
        $leaderB = User::factory()->teamLeader()->create();
        $employee = User::factory()->employee()->create();

        Evaluation::factory()->create([
            'user_id' => $employee->id,
            'team_leader_id' => $leaderA->id,
            'month' => 1,
            'year' => 2026,
            'total_score' => 80,
        ]);
        Evaluation::factory()->create([
            'user_id' => $employee->id,
            'team_leader_id' => $leaderB->id,
            'month' => 1,
            'year' => 2026,
            'total_score' => 100,
        ]);
        Evaluation::factory()->create([
            'user_id' => $employee->id,
            'team_leader_id' => $leaderA->id,
            'month' => 2,
            'year' => 2026,
            'total_score' => 70,
        ]);

        $avg = app(ReportService::class)->annualEvaluation($employee, 2026);

        // Month 1 combined 90, month 2 = 70 → year (90+70)/2 = 80 (not flat 83.33)
        $this->assertSame(80.0, $avg);
    }

    public function test_annual_evaluation_null_when_empty(): void
    {
        $employee = User::factory()->employee()->create();

        $this->assertNull(app(ReportService::class)->annualEvaluation($employee, 2026));
    }
}
