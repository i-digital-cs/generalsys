<?php

namespace Tests\Unit\Attendance;

use App\Services\Attendance\AttendanceGracePolicy;
use Tests\TestCase;

/**
 * Task 1 / 1.6 — 15-minute grace from effective date; threshold not deduction.
 */
class AttendanceGracePolicyTest extends TestCase
{
    private AttendanceGracePolicy $policy;

    private string $onPolicy = '2026-10-01';

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new AttendanceGracePolicy;
    }

    public function test_effective_date_boundary(): void
    {
        $this->assertFalse($this->policy->isActiveForAttendanceDate('2026-09-30'));
        $this->assertTrue($this->policy->isActiveForAttendanceDate('2026-10-01'));
        $this->assertTrue($this->policy->isActiveForAttendanceDate('2026-10-02'));
        $this->assertFalse($this->policy->isActiveForAttendanceDate(null));
        $this->assertFalse($this->policy->isActiveForAttendanceDate(''));
    }

    public function test_pre_effective_date_does_not_apply_grace_threshold(): void
    {
        $this->assertSame(10, $this->policy->countedLateMinutes(10, '2026-09-30'));
        $this->assertSame(15, $this->policy->countedLateMinutes(15, '2026-09-30'));
        $this->assertSame(0, $this->policy->exemptArrivalMinutes(10, '2026-09-30'));

        $balance = $this->policy->durationBalance(480, 460, 10, 10, '2026-09-30');
        $this->assertFalse($balance['grace_policy_active']);
        $this->assertSame(20, $balance['counted_deficit_minutes']);
        $this->assertSame(10, $balance['counted_late_minutes']);
    }

    public function test_late_grace_threshold_not_deduction(): void
    {
        $d = $this->onPolicy;
        $this->assertSame(0, $this->policy->countedLateMinutes(0, $d));
        $this->assertSame(0, $this->policy->countedLateMinutes(5, $d));
        $this->assertSame(0, $this->policy->countedLateMinutes(10, $d));
        $this->assertSame(0, $this->policy->countedLateMinutes(15, $d));
        $this->assertSame(16, $this->policy->countedLateMinutes(16, $d));
        $this->assertSame(20, $this->policy->countedLateMinutes(20, $d));
        $this->assertSame(30, $this->policy->countedLateMinutes(30, $d));
    }

    public function test_early_grace_threshold_not_deduction(): void
    {
        $d = $this->onPolicy;
        $this->assertSame(0, $this->policy->countedEarlyMinutes(0, $d));
        $this->assertSame(0, $this->policy->countedEarlyMinutes(15, $d));
        $this->assertSame(16, $this->policy->countedEarlyMinutes(16, $d));
        $this->assertSame(20, $this->policy->countedEarlyMinutes(20, $d));
    }

    public function test_example_both_within_grace_zero_deficit(): void
    {
        $balance = $this->policy->durationBalance(480, 460, 10, 10, $this->onPolicy);

        $this->assertSame(0, $balance['counted_late_minutes']);
        $this->assertSame(0, $balance['counted_early_minutes']);
        $this->assertSame(0, $balance['counted_deficit_minutes']);
        $this->assertSame(AttendanceGracePolicy::CLASS_BALANCED, $balance['classification']);
        $this->assertTrue($balance['grace_policy_active']);
    }

    public function test_example_late_exceeds_grace(): void
    {
        $balance = $this->policy->durationBalance(480, 464, 16, 0, $this->onPolicy);

        $this->assertSame(16, $balance['counted_late_minutes']);
        $this->assertSame(16, $balance['counted_deficit_minutes']);
        $this->assertSame(AttendanceGracePolicy::CLASS_DEFICIT, $balance['classification']);
    }

    public function test_example_early_exceeds_grace(): void
    {
        $balance = $this->policy->durationBalance(480, 464, 0, 16, $this->onPolicy);

        $this->assertSame(16, $balance['counted_early_minutes']);
        $this->assertSame(16, $balance['counted_deficit_minutes']);
        $this->assertSame(AttendanceGracePolicy::CLASS_DEFICIT, $balance['classification']);
    }

    public function test_example_both_exceed_grace(): void
    {
        $balance = $this->policy->durationBalance(480, 440, 20, 20, $this->onPolicy);

        $this->assertSame(20, $balance['counted_late_minutes']);
        $this->assertSame(20, $balance['counted_early_minutes']);
        $this->assertSame(40, $balance['counted_deficit_minutes']);
        $this->assertSame(AttendanceGracePolicy::CLASS_DEFICIT, $balance['classification']);
    }

    public function test_example_surplus_not_deficit_and_not_auto_ot(): void
    {
        $balance = $this->policy->durationBalance(480, 525, 0, 0, $this->onPolicy);

        $this->assertSame(0, $balance['counted_deficit_minutes']);
        $this->assertSame(45, $balance['counted_surplus_minutes']);
        $this->assertSame(AttendanceGracePolicy::CLASS_SURPLUS, $balance['classification']);
    }

    public function test_graces_are_independent(): void
    {
        $balance = $this->policy->durationBalance(480, 450, 10, 20, $this->onPolicy);

        $this->assertSame(0, $balance['counted_late_minutes']);
        $this->assertSame(20, $balance['counted_early_minutes']);
        $this->assertSame(10, $balance['exempt_arrival_minutes']);
        $this->assertSame(0, $balance['exempt_departure_minutes']);
        $this->assertSame(20, $balance['counted_deficit_minutes']);
    }

    public function test_exempt_minutes_never_create_surplus(): void
    {
        $balance = $this->policy->durationBalance(480, 480, 10, 0, $this->onPolicy);

        $this->assertSame(AttendanceGracePolicy::CLASS_BALANCED, $balance['classification']);
        $this->assertSame(0, $balance['counted_surplus_minutes']);
        $this->assertSame(0, $balance['counted_deficit_minutes']);
    }
}
