<?php

namespace Tests\Unit;

use App\Http\Requests\Employee\ClockLocationRules;
use App\Models\Attendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClockLocationRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_time_expects_office_proximity_for_employees(): void
    {
        $employee = User::factory()->employee()->create();

        $this->assertTrue(ClockLocationRules::expectsOfficeProximity(Attendance::SHIFT_FULL_TIME, $employee));
        $this->assertTrue(ClockLocationRules::isRequiredFor(Attendance::SHIFT_FULL_TIME, $employee));
    }

    public function test_online_shifts_do_not_expect_office_proximity(): void
    {
        $employee = User::factory()->employee()->create();

        foreach ([Attendance::SHIFT_ONLINE_HALF, Attendance::SHIFT_ONLINE_FULL, Attendance::SHIFT_EXTRA, Attendance::SHIFT_OVERTIME] as $shift) {
            $this->assertFalse(ClockLocationRules::expectsOfficeProximity($shift, $employee), $shift);
        }
    }

    public function test_drivers_always_expect_office_proximity(): void
    {
        $driver = User::factory()->driver()->create();

        foreach ([Attendance::SHIFT_FULL_TIME, Attendance::SHIFT_ONLINE_HALF, Attendance::SHIFT_ONLINE_FULL] as $shift) {
            $this->assertTrue(ClockLocationRules::expectsOfficeProximity($shift, $driver), $shift);
        }
    }
}
