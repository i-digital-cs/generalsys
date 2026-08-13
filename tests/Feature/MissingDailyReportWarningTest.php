<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\RestDay;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * reports:warn-missing scheduler command + dashboard warning cards.
 */
class MissingDailyReportWarningTest extends TestCase
{
    use RefreshDatabase;

    private User $leader;

    private User $employee;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-10 21:00:00');

        $this->leader = User::factory()->teamLeader()->create([
            'name_en' => 'Leader One',
            'language_preference' => 'en',
        ]);
        $this->employee = User::factory()->employee()->create([
            'team_leader_id' => $this->leader->id,
            'name_en' => 'Missing Employee',
            'language_preference' => 'en',
        ]);
        $this->manager = User::factory()->manager()->create([
            'language_preference' => 'en',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_same_day_run_warns_tl_and_managers_for_missing_employee(): void
    {
        $this->artisan('reports:warn-missing', ['offset' => 0])->assertSuccessful();

        // Employee missing → their TL + all managers warned (kind = today).
        $leaderData = $this->leader->notifications()->get()->pluck('data');
        $this->assertSame(1, $leaderData->count());
        $this->assertSame('report_missing_today', $leaderData[0]['kind']);
        $this->assertSame('2026-08-10', $leaderData[0]['date']);
        $this->assertSame($this->employee->id, $leaderData[0]['employee_id']);

        // Manager warned about the missing employee AND the missing TL (2 total).
        $managerData = $this->manager->notifications()->get()->pluck('data');
        $this->assertSame(2, $managerData->count());
        $this->assertTrue($managerData->every(fn (array $d) => $d['kind'] === 'report_missing_today'));
        $this->assertTrue($managerData->contains(fn (array $d) => $d['employee_id'] === $this->employee->id));
        $this->assertTrue($managerData->contains(fn (array $d) => $d['employee_id'] === $this->leader->id));
    }

    public function test_missing_tl_warns_managers_not_other_tls(): void
    {
        $otherLeader = User::factory()->teamLeader()->create();

        $this->artisan('reports:warn-missing', ['offset' => 0])->assertSuccessful();

        // Other TL is never warned about the missing TL.
        $this->assertSame(
            0,
            $otherLeader->notifications()->get()
                ->filter(fn ($n) => $n->data['employee_id'] === $this->leader->id)
                ->count()
        );

        // Managers are.
        $this->assertSame(
            1,
            $this->manager->notifications()->get()
                ->filter(fn ($n) => $n->data['employee_id'] === $this->leader->id)
                ->count()
        );
    }

    public function test_next_morning_run_sends_overdue_kind_for_yesterday(): void
    {
        Carbon::setTestNow('2026-08-11 08:00:00');

        $this->artisan('reports:warn-missing', ['offset' => 1])->assertSuccessful();

        $data = $this->leader->notifications()->get()->pluck('data');
        $this->assertSame(1, $data->count());
        $this->assertSame('report_missing_overdue', $data[0]['kind']);
        $this->assertSame('2026-08-10', $data[0]['date']);
    }

    public function test_nothing_sent_when_report_submitted(): void
    {
        $this->submitReport($this->employee, '2026-08-10');
        $this->submitReport($this->leader, '2026-08-10');

        $this->artisan('reports:warn-missing', ['offset' => 0])->assertSuccessful();

        $this->assertSame(0, $this->leader->notifications()->count());
        $this->assertSame(0, $this->manager->notifications()->count());
    }

    public function test_nothing_sent_on_rest_day(): void
    {
        RestDay::factory()->create([
            'user_id' => $this->employee->id,
            'date' => '2026-08-10',
            'created_by' => $this->manager->id,
        ]);
        RestDay::factory()->create([
            'user_id' => $this->leader->id,
            'date' => '2026-08-10',
            'created_by' => $this->manager->id,
        ]);

        $this->artisan('reports:warn-missing', ['offset' => 0])->assertSuccessful();

        $this->assertSame(0, $this->leader->notifications()->count());
        $this->assertSame(0, $this->manager->notifications()->count());
    }

    public function test_team_leader_dashboard_shows_warning_card(): void
    {
        app()->setLocale('en');

        $this->actingAs($this->leader)
            ->get(route('team-leader.dashboard'))
            ->assertOk()
            ->assertSee(__('dashboard.missing_reports.title'), false)
            ->assertSee('Missing Employee', false);
    }

    public function test_admin_dashboard_shows_warning_card_with_tl_and_employee(): void
    {
        app()->setLocale('en');

        $this->actingAs($this->manager)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(__('dashboard.missing_reports.title'), false)
            ->assertSee('Missing Employee', false)
            ->assertSee('Leader One', false);
    }

    public function test_employee_dashboard_does_not_show_warning_card(): void
    {
        app()->setLocale('en');

        $this->actingAs($this->employee)
            ->get(route('employee.dashboard'))
            ->assertOk()
            ->assertDontSee(__('dashboard.missing_reports.title'), false);
    }

    private function submitReport(User $user, string $date): void
    {
        Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => $date,
            'clock_in' => $date.' 10:00:00',
            'clock_out' => $date.' 18:00:00',
            'daily_report' => 'Done',
        ]);
    }
}
