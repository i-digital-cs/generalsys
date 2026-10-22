<?php

namespace Tests\Unit\Attendance;

use App\Exceptions\AttendanceException;
use App\Models\Attendance;
use App\Models\PayrollRecord;
use App\Models\User;
use App\Services\Attendance\AttendanceGracePolicy;
use App\Services\Attendance\AttendanceService;
use App\Services\Attendance\AttendanceTimeCalculator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Forensic regression matrix — Reda / Amir / Rahma / overnight / multi-shift.
 * Fixtures are anonymized recreations of verified production patterns (TEST only).
 */
class ForensicAttendanceRegressionTest extends TestCase
{
    use RefreshDatabase;

    private AttendanceService $service;

    private AttendanceTimeCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AttendanceService::class);
        $this->calculator = app(AttendanceTimeCalculator::class);
    }

    public function test_reda_misaligned_day_window_does_not_fabricate_checkout(): void
    {
        $reda = $this->makeRedaProfile();

        $this->service->clockIn($reda, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-10-20 23:03:15'));

        $this->assertNull($this->service->closeStaleOpenBeforeShiftStart(
            $reda,
            Carbon::parse('2026-10-21 11:45:00'),
            Attendance::SHIFT_FULL_TIME,
        ));

        $open = $this->service->openAttendance($reda);
        $this->assertNotNull($open);
        $this->assertNull($open->clock_out);
        $this->assertFalse((bool) $open->is_auto_clock_out);

        $closed = $this->service->clockOut($reda, Carbon::parse('2026-10-21 11:45:31'));
        $this->assertSame('2026-10-21 11:45:31', $closed->clock_out->format('Y-m-d H:i:s'));
        $this->assertFalse((bool) $closed->is_auto_clock_out);
        $this->assertGreaterThan(12.0, (float) $closed->raw_actual_hours);
    }

    public function test_amir_true_overnight_stays_open_until_manual_out(): void
    {
        $amir = $this->makeAmirProfile();

        $this->service->clockIn($amir, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-10-20 23:03:28'));

        // Before expected end (08:00) — overnight still running.
        $this->assertNull($this->service->closeStaleOpenBeforeShiftStart(
            $amir,
            Carbon::parse('2026-10-21 07:30:00'),
            Attendance::SHIFT_FULL_TIME,
        ));
        $this->assertNotNull($this->service->openAttendance($amir));

        $closed = $this->service->clockOut($amir, Carbon::parse('2026-10-21 11:45:31'));
        $this->assertSame('2026-10-21 11:45:31', $closed->clock_out->format('Y-m-d H:i:s'));
        $this->assertFalse((bool) $closed->is_auto_clock_out);
    }

    public function test_amir_overnight_stale_closes_at_expected_end_after_window(): void
    {
        $amir = $this->makeAmirProfile();

        $this->service->clockIn($amir, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-10-20 23:03:28'));

        // Next evening near next shift start — prior overnight ended at 08:00.
        $closed = $this->service->closeStaleOpenBeforeShiftStart(
            $amir,
            Carbon::parse('2026-10-21 19:30:00'),
            Attendance::SHIFT_FULL_TIME,
        );

        $this->assertNotNull($closed);
        $this->assertTrue((bool) $closed->is_auto_clock_out);
        $this->assertSame('2026-10-21 08:00:00', $closed->clock_out->format('Y-m-d H:i:s'));
    }

    public function test_rahma_evening_prefers_unused_half_not_completed_full(): void
    {
        $rahma = $this->makeRahmaProfile();

        $this->service->clockIn($rahma, Attendance::SHIFT_ONLINE_FULL, Carbon::parse('2026-10-21 10:09:29'));
        $this->service->clockOut($rahma, Carbon::parse('2026-10-21 12:05:45'));

        $evening = Carbon::parse('2026-10-21 20:57:59');
        $this->assertSame(Attendance::SHIFT_ONLINE_HALF, $this->service->preferredShiftType($rahma, $evening));
        $this->assertContains(Attendance::SHIFT_ONLINE_FULL, $this->service->availableShiftTypes($rahma, $evening));
        $this->assertContains(Attendance::SHIFT_ONLINE_HALF, $this->service->availableShiftTypes($rahma, $evening));

        $half = $this->service->clockIn($rahma, Attendance::SHIFT_ONLINE_HALF, $evening);
        $this->assertSame(Attendance::SHIFT_ONLINE_HALF, $half->shift_type);
        $this->assertNotSame(
            Attendance::query()->where('user_id', $rahma->id)->where('shift_type', Attendance::SHIFT_ONLINE_FULL)->value('id'),
            $half->id,
        );

        $this->service->clockOut($rahma, Carbon::parse('2026-10-21 22:58:20'));

        $this->assertSame(2, Attendance::query()->where('user_id', $rahma->id)->whereDate('date', '2026-10-21')->count());
    }

    public function test_legitimate_multi_segment_same_shift_still_allowed(): void
    {
        $employee = User::factory()->employee()->create([
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
            'shift_types_default' => [[
                'type' => Attendance::SHIFT_FULL_TIME,
                'start' => '10:00',
                'end' => '18:00',
                'expected_hours' => 8,
            ]],
        ]);

        $this->service->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-10-21 10:00:00'));
        $this->service->clockOut($employee, Carbon::parse('2026-10-21 12:00:00'));

        // Explicit mid-shift re-entry on same type (available still includes FULL).
        $this->assertContains(Attendance::SHIFT_FULL_TIME, $this->service->availableShiftTypes(
            $employee,
            Carbon::parse('2026-10-21 13:00:00'),
        ));
        $row = $this->service->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-10-21 13:00:00'));
        $this->service->clockOut($employee, Carbon::parse('2026-10-21 18:00:00'));

        $fresh = $row->fresh(['segments']);
        $this->assertCount(2, $fresh->segments);
        $this->assertSame(1, Attendance::query()->where('user_id', $employee->id)->count());
    }

    public function test_overnight_profiles_resolve_next_day_end(): void
    {
        $cases = [
            ['20:00', '08:00', '2026-10-20 20:00:00', '2026-10-21 08:00:00'],
            ['22:00', '10:00', '2026-10-20 22:00:00', '2026-10-21 10:00:00'],
            ['08:00', '20:00', '2026-10-20 08:00:00', '2026-10-20 20:00:00'],
            ['00:00', '12:00', '2026-10-20 00:00:00', '2026-10-20 12:00:00'],
        ];

        foreach ($cases as [$start, $end, $expectStart, $expectEnd]) {
            $user = User::factory()->employee()->create([
                'work_start_time' => $start,
                'work_end_time' => $end,
                'shift_types_default' => [[
                    'type' => Attendance::SHIFT_FULL_TIME,
                    'start' => $start,
                    'end' => $end,
                    'expected_hours' => 12,
                ]],
            ]);
            $at = Carbon::parse('2026-10-20 12:00:00');
            $this->assertSame(
                $expectStart,
                $this->service->expectedStartFor($user, Attendance::SHIFT_FULL_TIME, $at)?->format('Y-m-d H:i:s'),
                "start {$start}-{$end}",
            );
            $this->assertSame(
                $expectEnd,
                $this->service->expectedEndFor($user, Attendance::SHIFT_FULL_TIME, $at)?->format('Y-m-d H:i:s'),
                "end {$start}-{$end}",
            );
        }
    }

    public function test_missing_checkout_stays_null_until_real_close(): void
    {
        $employee = User::factory()->employee()->create([
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
        ]);
        $row = $this->service->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-10-21 10:00:00'));
        $this->assertNull($row->clock_out);
        $this->assertNull($row->fresh()->clock_out);
        $this->assertTrue($row->fresh(['segments'])->hasOpenSegment());
    }

    public function test_duplicate_clock_in_while_open_is_rejected(): void
    {
        $employee = User::factory()->employee()->create([
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
        ]);
        $this->service->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-10-21 10:00:00'));

        $this->expectException(AttendanceException::class);
        $this->service->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-10-21 10:05:00'));
    }

    public function test_recalculate_is_idempotent_for_closed_row(): void
    {
        $employee = User::factory()->employee()->create([
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
        ]);
        $this->service->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-10-21 10:00:00'));
        $closed = $this->service->clockOut($employee, Carbon::parse('2026-10-21 18:00:00'));

        $first = $this->calculator->recalculate($closed->fresh(['segments', 'user']), $this->service);
        $second = $this->calculator->recalculate($first->fresh(['segments', 'user']), $this->service);

        $this->assertSame((string) $first->raw_actual_hours, (string) $second->raw_actual_hours);
        $this->assertSame((string) $first->actual_hours, (string) $second->actual_hours);
        $this->assertSame((int) $first->late_minutes, (int) $second->late_minutes);
        $this->assertSame($first->clock_in->format('c'), $second->clock_in->format('c'));
        $this->assertSame($first->clock_out->format('c'), $second->clock_out->format('c'));
    }

    public function test_grace_policy_15_vs_16_preserved(): void
    {
        $grace = app(AttendanceGracePolicy::class);
        $this->assertSame(0, $grace->countedLateMinutes(15, '2026-10-01'));
        $this->assertSame(16, $grace->countedLateMinutes(16, '2026-10-01'));
        $this->assertSame(0, $grace->countedEarlyMinutes(15, '2026-10-01'));
        $this->assertSame(16, $grace->countedEarlyMinutes(16, '2026-10-01'));
    }

    public function test_approved_payroll_row_is_not_mutated_by_attendance_recalc(): void
    {
        $employee = User::factory()->employee()->create([
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
            'base_salary' => 3000,
        ]);
        $this->service->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-10-05 10:00:00'));
        $this->service->clockOut($employee, Carbon::parse('2026-10-05 18:00:00'));

        $manager = User::factory()->manager()->create();
        $payroll = PayrollRecord::factory()->create([
            'user_id' => $employee->id,
            'month' => 10,
            'year' => 2026,
            'status' => PayrollRecord::STATUS_APPROVED,
            'base_salary' => 3000,
            'net_pay' => 2808,
            'currency' => 'EGP',
            'created_by' => $manager->id,
        ]);
        $beforeNet = (string) $payroll->fresh()->net_pay;
        $beforeStatus = (string) $payroll->fresh()->status;

        $att = Attendance::query()->where('user_id', $employee->id)->first();
        $this->calculator->recalculate($att->fresh(['segments', 'user']), $this->service);

        $this->assertSame($beforeNet, (string) $payroll->fresh()->net_pay);
        $this->assertSame($beforeStatus, (string) $payroll->fresh()->status);
    }

    public function test_out_of_order_segment_times_rejected_by_supervisor_bump(): void
    {
        $employee = User::factory()->employee()->create([
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
        ]);
        $leader = User::factory()->teamLeader()->create();
        $att = $this->service->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-10-21 10:00:00'));

        // Supervisor close at or before clock-in is bumped +1 minute (explicit force path).
        $closed = $this->service->supervisorClockOut($att, $leader, Carbon::parse('2026-10-21 09:59:00'));
        $this->assertSame('2026-10-21 10:01:00', $closed->clock_out->format('Y-m-d H:i:s'));
        $this->assertSame($leader->id, $closed->force_closed_by);
        $this->assertFalse((bool) $closed->is_auto_clock_out);
    }

    public function test_identical_timestamps_different_employees_independent(): void
    {
        $a = $this->makeAmirProfile();
        $b = $this->makeRedaProfile();
        $atIn = Carbon::parse('2026-10-20 23:03:15');
        $atOut = Carbon::parse('2026-10-21 11:45:00');

        $this->service->clockIn($a, Attendance::SHIFT_FULL_TIME, $atIn);
        $this->service->clockIn($b, Attendance::SHIFT_FULL_TIME, $atIn);
        $this->service->clockOut($a, $atOut);
        $this->service->clockOut($b, $atOut);

        $rowA = Attendance::query()->where('user_id', $a->id)->sole();
        $rowB = Attendance::query()->where('user_id', $b->id)->sole();
        $this->assertTrue($rowA->clock_in->equalTo($atIn));
        $this->assertTrue($rowB->clock_in->equalTo($atIn));
        $this->assertTrue($rowA->clock_out->equalTo($atOut));
        $this->assertTrue($rowB->clock_out->equalTo($atOut));
        $this->assertEquals((float) $rowA->raw_actual_hours, (float) $rowB->raw_actual_hours);
    }

    public function test_header_mirrors_first_and_last_segment_not_sum_as_single_span_hours(): void
    {
        $rahma = $this->makeRahmaProfile();
        $this->service->clockIn($rahma, Attendance::SHIFT_ONLINE_FULL, Carbon::parse('2026-10-21 10:00:00'));
        $this->service->clockOut($rahma, Carbon::parse('2026-10-21 12:00:00'));
        $this->service->clockIn($rahma, Attendance::SHIFT_ONLINE_FULL, Carbon::parse('2026-10-21 21:00:00'));
        $closed = $this->service->clockOut($rahma, Carbon::parse('2026-10-21 23:00:00'));

        $this->assertSame('2026-10-21 10:00:00', $closed->clock_in->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-21 23:00:00', $closed->clock_out->format('Y-m-d H:i:s'));
        $this->assertEqualsWithDelta(4.0, (float) $closed->raw_actual_hours, 0.01);
        $this->assertCount(2, $closed->segments);
    }

    private function makeRedaProfile(): User
    {
        return User::factory()->employee()->create([
            'name_en' => 'FORENSIC REDA',
            'work_start_time' => '00:00',
            'work_end_time' => '12:00',
            'shift_type_default' => Attendance::SHIFT_FULL_TIME,
            'shift_types_default' => [[
                'type' => Attendance::SHIFT_FULL_TIME,
                'start' => '00:00',
                'end' => '12:00',
                'expected_hours' => 12,
            ]],
        ]);
    }

    private function makeAmirProfile(): User
    {
        return User::factory()->employee()->create([
            'name_en' => 'FORENSIC AMIR',
            'work_start_time' => '20:00',
            'work_end_time' => '08:00',
            'shift_type_default' => Attendance::SHIFT_FULL_TIME,
            'shift_types_default' => [[
                'type' => Attendance::SHIFT_FULL_TIME,
                'start' => '20:00',
                'end' => '08:00',
                'expected_hours' => 12,
            ]],
        ]);
    }

    private function makeRahmaProfile(): User
    {
        return User::factory()->employee()->create([
            'name_en' => 'FORENSIC RAHMA',
            'work_start_time' => '10:00',
            'work_end_time' => '12:00',
            'shift_type_default' => Attendance::SHIFT_ONLINE_FULL,
            'shift_types_default' => [
                [
                    'type' => Attendance::SHIFT_ONLINE_FULL,
                    'start' => '10:00',
                    'end' => '12:00',
                    'expected_hours' => 2,
                ],
                [
                    'type' => Attendance::SHIFT_ONLINE_HALF,
                    'start' => '21:00',
                    'end' => '00:00',
                    'expected_hours' => 3,
                ],
            ],
        ]);
    }
}
