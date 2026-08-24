<?php

namespace Tests\Feature\Employee;

use App\Models\Attendance;
use App\Models\OfficialHoliday;
use App\Models\RestDay;
use App\Models\User;
use App\Services\Attendance\AttendanceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T-3.5 — Friday / rest-day / holiday work badges visible when flags apply.
 */
class AttendanceFlagsDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_clock_screen_shows_work_flag_badges(): void
    {
        app()->setLocale('en');

        // Freeze "now" on the punch day so R5 stale force-close does not clear the open shift.
        Carbon::setTestNow(Carbon::parse('2026-08-07 12:00:00'));

        $employee = User::factory()->employee()->create([
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
            'language_preference' => 'en',
        ]);

        RestDay::factory()->create([
            'user_id' => $employee->id,
            'date' => '2026-08-07',
        ]);
        OfficialHoliday::create([
            'date' => '2026-08-07',
            'name_ar' => 'عطلة',
            'name_en' => 'Holiday',
        ]);

        app(AttendanceService::class)->clockIn(
            $employee,
            Attendance::SHIFT_FULL_TIME,
            Carbon::parse('2026-08-07 10:00:00'),
        );

        $this->actingAs($employee)
            ->get(route('employee.clock'))
            ->assertOk()
            ->assertSee(__('clock.flags.friday'), false)
            ->assertSee(__('clock.flags.rest_day'), false)
            ->assertSee(__('clock.flags.holiday'), false);

        Carbon::setTestNow();
    }

    public function test_dashboard_today_sessions_show_flags(): void
    {
        app()->setLocale('en');

        Carbon::setTestNow(Carbon::parse('2026-08-07 12:00:00'));

        $employee = User::factory()->employee()->create([
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
            'language_preference' => 'en',
        ]);

        RestDay::factory()->create([
            'user_id' => $employee->id,
            'date' => '2026-08-07',
        ]);

        app(AttendanceService::class)->clockIn(
            $employee,
            Attendance::SHIFT_FULL_TIME,
            Carbon::parse('2026-08-07 10:00:00'),
        );

        $this->actingAs($employee)
            ->get(route('employee.dashboard'))
            ->assertOk()
            ->assertSee(__('clock.flags.friday'), false)
            ->assertSee(__('clock.flags.rest_day'), false);

        Carbon::setTestNow();
    }
}
