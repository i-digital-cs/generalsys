<?php

namespace Tests\Unit\Attendance;

use App\Models\Attendance;
use App\Services\Attendance\ShiftWindowValidator;
use Tests\TestCase;

/**
 * R9 — shift window vs planned hours compatibility.
 */
class ShiftWindowValidatorTest extends TestCase
{
    public function test_same_day_eight_hour_window_is_compatible(): void
    {
        $v = app(ShiftWindowValidator::class);

        $this->assertTrue($v->isCompatible(Attendance::SHIFT_FULL_TIME, '09:00', '17:00'));
        $this->assertTrue($v->isCompatible(Attendance::SHIFT_FULL_TIME, '10:00', '18:00'));
    }

    public function test_true_overnight_matching_planned_is_compatible(): void
    {
        $v = app(ShiftWindowValidator::class);

        $this->assertTrue($v->isCompatible(Attendance::SHIFT_FULL_TIME, '22:00', '06:00'));
        $this->assertSame(8.0, $v->windowHours('22:00', '06:00'));
    }

    public function test_mismatched_overnight_sixteen_hour_window_is_rejected(): void
    {
        $v = app(ShiftWindowValidator::class);

        $this->assertFalse($v->isCompatible(Attendance::SHIFT_FULL_TIME, '09:00', '01:00'));
        $this->assertSame(16.0, $v->windowHours('09:00', '01:00'));
        $this->assertSame('17:00', $v->suggestedEnd('09:00', 8.0));
    }

    public function test_online_half_rejects_oversized_window(): void
    {
        $v = app(ShiftWindowValidator::class);

        $this->assertFalse($v->isCompatible(Attendance::SHIFT_ONLINE_HALF, '06:00', '18:00'));
        $this->assertTrue($v->isCompatible(Attendance::SHIFT_ONLINE_HALF, '06:00', '10:00'));
    }

    public function test_assignment_override_allows_longer_window_when_hours_match(): void
    {
        $v = app(ShiftWindowValidator::class);

        $this->assertTrue($v->isCompatible(Attendance::SHIFT_FULL_TIME, '09:00', '01:00', 16.0));
        $this->assertFalse($v->isCompatible(Attendance::SHIFT_FULL_TIME, '09:00', '01:00', 8.0));
    }
}
