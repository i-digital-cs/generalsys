<?php

namespace Tests\Feature\Performance;

use App\Models\Attendance;
use App\Models\RestDay;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Models\Vacation;
use App\Services\Attendance\HoursComparisonService;
use App\Services\Employees\TeamMembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Task 4 — teamMonthSummary bulk load: parity with monthForUser + bounded query growth.
 *
 * Engine: PHPUnit SQLite in-memory (synthetic). Not a Hostinger MySQL latency benchmark.
 * Isolated MYSQL_TEST_DATABASE was unavailable on this host.
 */
class TeamMonthSummaryPerformanceTest extends TestCase
{
    use RefreshDatabase;

    private HoursComparisonService $hours;

    private TeamMembershipService $membership;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hours = app(HoursComparisonService::class);
        $this->membership = app(TeamMembershipService::class);
    }

    /**
     * @return array{0: User, 1: Collection<int, User>}
     */
    private function seedTeam(int $n, string $ym = '2026-08'): array
    {
        $leader = User::factory()->teamLeader()->create();
        $members = User::factory()->employee()->count($n)->create([
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
        ]);

        foreach ($members as $member) {
            $this->membership->syncLeaders($member, [$leader->id]);
            RestDay::factory()->create([
                'user_id' => $member->id,
                'date' => $ym.'-07',
                'created_by' => $leader->id,
            ]);
            for ($day = 1; $day <= 20; $day++) {
                if ($day === 7) {
                    continue;
                }
                $date = sprintf('%s-%02d', $ym, $day);
                Attendance::factory()->create([
                    'user_id' => $member->id,
                    'date' => $date,
                    'shift_type' => Attendance::SHIFT_FULL_TIME,
                    'clock_in' => $date.' 10:00:00',
                    'clock_out' => $date.' 18:00:00',
                    'actual_hours' => 8,
                    'credited_hours' => 8,
                    'raw_actual_hours' => 8,
                    'planned_hours' => 8,
                    'status' => Attendance::STATUS_PRESENT,
                ]);
            }
        }

        return [$leader, $members];
    }

    public function test_team_month_summary_matches_month_for_user_totals(): void
    {
        [$leader, $members] = $this->seedTeam(5, '2026-08');

        $outsider = User::factory()->employee()->create([
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
        ]);
        Attendance::factory()->create([
            'user_id' => $outsider->id,
            'date' => '2026-08-02',
            'shift_type' => Attendance::SHIFT_FULL_TIME,
            'clock_in' => '2026-08-02 10:00:00',
            'clock_out' => '2026-08-02 18:00:00',
            'actual_hours' => 8,
            'credited_hours' => 8,
            'planned_hours' => 8,
            'status' => Attendance::STATUS_PRESENT,
        ]);

        $summary = $this->hours->teamMonthSummary($leader, '2026-08');
        $this->assertCount(5, $summary);
        $this->assertFalse($summary->contains(fn (array $row) => (int) $row['user']->id === (int) $outsider->id));

        foreach ($summary as $row) {
            $month = $this->hours->monthForUser($row['user'], '2026-08');
            $this->assertSame($month['totals']['planned'], $row['planned']);
            $this->assertSame($month['totals']['actual'], $row['actual']);
            $this->assertSame($month['totals']['pct'], $row['pct']);
        }

        $this->assertSame(
            $members->pluck('id')->sort()->values()->all(),
            $summary->pluck('user.id')->sort()->values()->all(),
        );
    }

    public function test_empty_team_returns_empty_collection(): void
    {
        $leader = User::factory()->teamLeader()->create();
        $summary = $this->hours->teamMonthSummary($leader, '2026-08');
        $this->assertCount(0, $summary);
    }

    public function test_vacation_and_assignment_parity_with_month_for_user(): void
    {
        $leader = User::factory()->teamLeader()->create();
        $employee = User::factory()->employee()->create([
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
        ]);
        $this->membership->syncLeaders($employee, [$leader->id]);

        Vacation::factory()->create([
            'user_id' => $employee->id,
            'type' => Vacation::TYPE_ANNUAL,
            'start_date' => '2026-10-05',
            'end_date' => '2026-10-06',
            'days_requested' => 2,
            'status' => Vacation::STATUS_APPROVED,
            'team_leader_id' => $leader->id,
        ]);

        ShiftAssignment::factory()->create([
            'user_id' => $employee->id,
            'date' => '2026-10-08',
            'shift_type' => Attendance::SHIFT_ONLINE_HALF,
            'expected_hours' => 4,
            'expected_start_time' => '10:00',
            'expected_end_time' => '14:00',
            'status' => ShiftAssignment::STATUS_SCHEDULED,
        ]);

        Attendance::factory()->create([
            'user_id' => $employee->id,
            'date' => '2026-10-08',
            'shift_type' => Attendance::SHIFT_ONLINE_HALF,
            'clock_in' => '2026-10-08 10:00:00',
            'clock_out' => '2026-10-08 14:00:00',
            'actual_hours' => 4,
            'credited_hours' => 4,
            'raw_actual_hours' => 4,
            'planned_hours' => 4,
            'status' => Attendance::STATUS_PRESENT,
        ]);

        $summary = $this->hours->teamMonthSummary($leader, '2026-10')->first();
        $month = $this->hours->monthForUser($employee, '2026-10');

        $this->assertNotNull($summary);
        $this->assertSame($month['totals']['planned'], $summary['planned']);
        $this->assertSame($month['totals']['actual'], $summary['actual']);
        $this->assertSame($month['totals']['pct'], $summary['pct']);
        $this->assertSame(2, $month['totals']['vacation_days']);
    }

    public function test_team_month_summary_query_count_is_bounded_not_linear(): void
    {
        $budgets = [];

        foreach ([1, 10, 50] as $n) {
            // Isolate each size on a fresh schema via RefreshDatabase already applied;
            // re-seed by wiping users is heavy — use separate leaders in same DB is fine
            // for relative growth because queries are scoped by visible team only.
            [$leader] = $this->seedTeam($n, '2026-09');

            DB::flushQueryLog();
            DB::enableQueryLog();
            $t0 = hrtime(true);
            $rows = $this->hours->teamMonthSummary($leader, '2026-09');
            $ms = (hrtime(true) - $t0) / 1e6;
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();

            $this->assertCount($n, $rows);
            $budgets[$n] = ['queries' => $queries, 'ms' => round($ms, 2)];

            // Hard ceiling: must not look like ~7×N (legacy per-employee monthForUser).
            $this->assertLessThanOrEqual(
                20,
                $queries,
                "teamMonthSummary({$n}) query count {$queries} exceeded bounded budget",
            );
        }

        // Growth from 10→50 employees must not approach 5× queries.
        $this->assertLessThanOrEqual(
            (int) ceil($budgets[10]['queries'] * 2.5),
            $budgets[50]['queries'],
            'Query growth from 10 to 50 employees should stay bounded: '.json_encode($budgets),
        );

        // Expose measurements in assertion message for the performance doc.
        $this->assertTrue(true, 'Measured teamMonthSummary budgets: '.json_encode($budgets));
    }

    public function test_pre_october_month_parity_unchanged(): void
    {
        [$leader, $members] = $this->seedTeam(2, '2026-09');
        $employee = $members->first();

        $summary = $this->hours->teamMonthSummary($leader, '2026-09')
            ->firstWhere(fn (array $row) => (int) $row['user']->id === (int) $employee->id);
        $month = $this->hours->monthForUser($employee, '2026-09');

        $this->assertSame($month['totals']['planned'], $summary['planned']);
        $this->assertSame($month['totals']['actual'], $summary['actual']);
    }
}
