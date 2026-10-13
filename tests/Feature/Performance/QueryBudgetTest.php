<?php

namespace Tests\Feature\Performance;

use App\Models\Attendance;
use App\Models\RestDay;
use App\Models\User;
use App\Models\Vacation;
use App\Services\Attendance\HoursComparisonService;
use App\Services\Employees\TeamMembershipService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 7 — synthetic query-count budgets (not production latency benchmarks).
 * Dataset: small fixture in SQLite; budgets enforce N+1 elimination.
 */
class QueryBudgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_month_for_user_stays_under_query_budget(): void
    {
        $user = User::factory()->employee()->create([
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
        ]);

        // Spread rest days across the month (previously 1 query per day via isRestDate).
        foreach (['2026-08-01', '2026-08-07', '2026-08-14', '2026-08-21', '2026-08-28'] as $d) {
            RestDay::factory()->create([
                'user_id' => $user->id,
                'date' => $d,
                'created_by' => $user->id,
            ]);
        }

        for ($day = 2; $day <= 30; $day++) {
            if (in_array($day, [1, 7, 14, 21, 28], true)) {
                continue;
            }
            $date = sprintf('2026-08-%02d', $day);
            Attendance::factory()->create([
                'user_id' => $user->id,
                'date' => $date,
                'shift_type' => Attendance::SHIFT_FULL_TIME,
                'clock_in' => $date.' 10:00:00',
                'clock_out' => $date.' 18:00:00',
                'actual_hours' => 8,
                'credited_hours' => 8,
                'planned_hours' => 8,
                'status' => Attendance::STATUS_PRESENT,
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $month = app(HoursComparisonService::class)->monthForUser($user, '2026-08');
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertCount(31, $month['days']);
        $this->assertSame(5, $month['totals']['rest_days']);
        // Before Phase 7: ~35+ (31× isRestDate). After: assignments + attendances + vacation + rest month = ≤15.
        $this->assertLessThanOrEqual(15, $queries, "monthForUser query count was {$queries}");
    }

    public function test_team_today_status_batches_rest_and_vacation(): void
    {
        $leader = User::factory()->teamLeader()->create();
        $members = User::factory()->employee()->count(8)->create();
        $membership = app(TeamMembershipService::class);
        foreach ($members as $member) {
            $membership->syncLeaders($member, [$leader->id]);
            RestDay::factory()->create([
                'user_id' => $member->id,
                'date' => '2026-08-03',
                'created_by' => $leader->id,
            ]);
        }

        $onVacation = $members->first();
        Vacation::factory()->create([
            'user_id' => $onVacation->id,
            'type' => Vacation::TYPE_ANNUAL,
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-03',
            'days_requested' => 1,
            'status' => Vacation::STATUS_APPROVED,
            'team_leader_id' => $leader->id,
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $rows = app(HoursComparisonService::class)->teamTodayStatus(
            $leader,
            Carbon::parse('2026-08-03 12:00:00', config('app.timezone')),
        );
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertCount(8, $rows);
        // Before: 8× isRestDate + 8× approvedVacationDates. After: 1 rest + 1 vacation batch.
        $this->assertLessThanOrEqual(25, $queries, "teamTodayStatus query count was {$queries}");
    }

    public function test_team_month_summary_stays_under_bounded_budget(): void
    {
        $leader = User::factory()->teamLeader()->create();
        $members = User::factory()->employee()->count(10)->create([
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
        ]);
        $membership = app(TeamMembershipService::class);
        foreach ($members as $member) {
            $membership->syncLeaders($member, [$leader->id]);
            RestDay::factory()->create([
                'user_id' => $member->id,
                'date' => '2026-08-07',
                'created_by' => $leader->id,
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $rows = app(HoursComparisonService::class)->teamMonthSummary($leader, '2026-08');
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertCount(10, $rows);
        // Legacy: ~7×N monthForUser. Bulk: visibleEmployees + assignments + attendances + rest + vacation.
        $this->assertLessThanOrEqual(20, $queries, "teamMonthSummary query count was {$queries}");
    }
}
