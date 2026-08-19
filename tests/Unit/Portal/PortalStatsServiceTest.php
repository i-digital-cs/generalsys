<?php

namespace Tests\Unit\Portal;

use App\Models\Attendance;
use App\Models\User;
use App\Services\Employees\TeamMembershipService;
use App\Services\Portal\PortalStatsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalStatsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-08-19 12:00:00', config('app.timezone')));
        app()->setLocale('en');
    }

    public function test_company_snapshot_counts_clocks_and_hours_without_other_teams_for_tl(): void
    {
        $manager = User::factory()->manager()->create();
        $leader = User::factory()->teamLeader()->create();
        $inTeam = User::factory()->employee()->create();
        $outsider = User::factory()->employee()->create();
        app(TeamMembershipService::class)->syncLeaders($inTeam, [$leader->id]);

        Attendance::factory()->create([
            'user_id' => $inTeam->id,
            'date' => '2026-08-19',
            'clock_in' => '2026-08-19 10:20:00',
            'clock_out' => null,
            'late_minutes' => 20,
            'status' => Attendance::STATUS_LATE,
            'planned_hours' => 8,
            'actual_hours' => 0,
            'daily_report' => 'ok',
        ]);
        Attendance::factory()->create([
            'user_id' => $outsider->id,
            'date' => '2026-08-19',
            'clock_in' => '2026-08-19 10:00:00',
            'clock_out' => '2026-08-19 18:00:00',
            'late_minutes' => 0,
            'status' => Attendance::STATUS_PRESENT,
            'planned_hours' => 8,
            'actual_hours' => 8,
            'daily_report' => 'ok',
        ]);

        $svc = app(PortalStatsService::class);
        $company = $svc->snapshot($manager);
        $team = $svc->snapshot($leader);

        $this->assertSame('company', $company['scope']);
        $this->assertSame(3, $company['cards']['staff']);
        $this->assertSame(2, $company['cards']['present_today']);
        $this->assertSame(1, $company['cards']['late_today']);
        $this->assertSame(1, $company['cards']['open_punches']);
        $this->assertSame(8.0, $company['hours']['actual']);

        $this->assertSame('team', $team['scope']);
        $this->assertSame(2, $team['cards']['staff']);
        $this->assertSame(1, $team['cards']['present_today']);
        $this->assertSame(1, $team['cards']['late_today']);
        $this->assertSame(1, $team['cards']['open_punches']);
        $this->assertSame(0.0, $team['hours']['actual']);

        $todayBar = collect($company['trend'])->firstWhere('date', '2026-08-19');
        $this->assertSame(2, $todayBar['value']);
    }
}
