<?php

namespace Tests\Unit\Services;

use App\Models\Attendance;
use App\Models\OfficialHoliday;
use App\Models\RestDay;
use App\Models\User;
use App\Models\Vacation;
use App\Services\Attendance\MissingDailyReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Missing daily report detection: no-show / blank report = missing;
 * rest days, approved vacations, official holidays excluded.
 */
class MissingDailyReportServiceTest extends TestCase
{
    use RefreshDatabase;

    private const DATE = '2026-08-10';

    private MissingDailyReportService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(MissingDailyReportService::class);
    }

    public function test_worked_without_report_is_missing(): void
    {
        $employee = User::factory()->employee()->create();
        Attendance::factory()->create([
            'user_id' => $employee->id,
            'date' => self::DATE,
            'clock_in' => self::DATE.' 10:00:00',
            'clock_out' => self::DATE.' 18:00:00',
            'daily_report' => null,
        ]);

        $missing = $this->service->missingPeopleForDate(Carbon::parse(self::DATE));

        $this->assertTrue($missing->contains(fn (User $u) => $u->id === $employee->id));
    }

    public function test_no_show_is_missing(): void
    {
        $employee = User::factory()->employee()->create();

        $missing = $this->service->missingPeopleForDate(Carbon::parse(self::DATE));

        $this->assertTrue($missing->contains(fn (User $u) => $u->id === $employee->id));
    }

    public function test_submitted_report_is_not_missing(): void
    {
        $employee = User::factory()->employee()->create();
        Attendance::factory()->create([
            'user_id' => $employee->id,
            'date' => self::DATE,
            'clock_in' => self::DATE.' 10:00:00',
            'clock_out' => self::DATE.' 18:00:00',
            'daily_report' => 'Finished the work',
        ]);

        $missing = $this->service->missingPeopleForDate(Carbon::parse(self::DATE));

        $this->assertFalse($missing->contains(fn (User $u) => $u->id === $employee->id));
    }

    public function test_rest_day_is_excluded(): void
    {
        $employee = User::factory()->employee()->create();
        RestDay::factory()->create([
            'user_id' => $employee->id,
            'date' => self::DATE,
        ]);

        $missing = $this->service->missingPeopleForDate(Carbon::parse(self::DATE));

        $this->assertFalse($missing->contains(fn (User $u) => $u->id === $employee->id));
    }

    public function test_approved_vacation_is_excluded(): void
    {
        $employee = User::factory()->employee()->create();
        Vacation::factory()->create([
            'user_id' => $employee->id,
            'start_date' => self::DATE,
            'end_date' => self::DATE,
            'days_requested' => 1,
            'status' => Vacation::STATUS_APPROVED,
        ]);

        $missing = $this->service->missingPeopleForDate(Carbon::parse(self::DATE));

        $this->assertFalse($missing->contains(fn (User $u) => $u->id === $employee->id));
    }

    public function test_official_holiday_is_excluded(): void
    {
        $employee = User::factory()->employee()->create();
        OfficialHoliday::create([
            'date' => self::DATE,
            'name_ar' => 'عيد رسمي',
            'name_en' => 'Official Holiday',
        ]);

        $missing = $this->service->missingPeopleForDate(Carbon::parse(self::DATE));

        $this->assertFalse($missing->contains(fn (User $u) => $u->id === $employee->id));
    }

    public function test_tl_missing_report_visible_to_manager_not_to_other_tl(): void
    {
        $leader = User::factory()->teamLeader()->create();
        $otherLeader = User::factory()->teamLeader()->create();
        $manager = User::factory()->manager()->create();

        $managerScope = $this->service->missingForActor($manager, Carbon::parse(self::DATE));
        $otherScope = $this->service->missingForActor($otherLeader, Carbon::parse(self::DATE));

        $this->assertTrue($managerScope->contains(fn (User $u) => $u->id === $leader->id));
        $this->assertFalse($otherScope->contains(fn (User $u) => $u->id === $leader->id));
    }

    public function test_employee_of_tl_a_not_visible_to_tl_b(): void
    {
        $leaderA = User::factory()->teamLeader()->create();
        $leaderB = User::factory()->teamLeader()->create();
        $employee = User::factory()->employee()->create(['team_leader_id' => $leaderA->id]);

        $scopeA = $this->service->missingForActor($leaderA, Carbon::parse(self::DATE));
        $scopeB = $this->service->missingForActor($leaderB, Carbon::parse(self::DATE));

        $this->assertTrue($scopeA->contains(fn (User $u) => $u->id === $employee->id));
        $this->assertFalse($scopeB->contains(fn (User $u) => $u->id === $employee->id));
    }

    public function test_employee_scope_sees_nothing(): void
    {
        $employee = User::factory()->employee()->create();

        $this->assertTrue($this->service->missingForActor($employee, Carbon::parse(self::DATE))->isEmpty());
    }
}
