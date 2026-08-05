<?php

namespace Tests\Unit;

use App\Exceptions\AttendanceException;
use App\Models\Attendance;
use App\Models\OfficialHoliday;
use App\Models\RestDay;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Services\Attendance\AttendanceService;
use App\Services\Attendance\DailyReportService;
use App\Services\Attendance\ShiftConfig;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Gate 2.1–2.2 — planned/actual hours, overnight, late/early minutes, day flags.
 */
class AttendanceServiceTest extends TestCase
{
    use RefreshDatabase;

    private AttendanceService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(AttendanceService::class);
    }

    public function test_planned_hours_profiles(): void
    {
        $config = new ShiftConfig;

        $this->assertSame(8.0, $config->plannedHours(Attendance::SHIFT_FULL_TIME));
        $this->assertSame(4.0, $config->plannedHours(Attendance::SHIFT_ONLINE_HALF));
        $this->assertSame(12.0, $config->plannedHours(Attendance::SHIFT_ONLINE_FULL));
        $this->assertSame(['06:00', '10:00'], $config->defaultWindow(Attendance::SHIFT_ONLINE_HALF));
        $this->assertSame(['10:00', '18:00'], $config->defaultWindow(Attendance::SHIFT_FULL_TIME));
        $this->assertSame(['10:00', '22:00'], $config->defaultWindow(Attendance::SHIFT_ONLINE_FULL));
    }

    public function test_clock_in_blocked_until_previous_day_report_submitted(): void
    {
        $employee = User::factory()->employee()->create([
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
        ]);

        $this->service->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-08-03 10:00:00'));
        $this->service->clockOut($employee, Carbon::parse('2026-08-03 18:00:00'));

        // Next day without a report → blocked.
        try {
            $this->service->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-08-04 10:00:00'));
            $this->fail('Expected AttendanceException for missing daily report.');
        } catch (AttendanceException $e) {
            $this->assertSame('clock.errors.report_required', $e->getMessage());
        }

        // Report submitted → clock-in allowed again.
        app(DailyReportService::class)
            ->submit($employee, '2026-08-03', 'Finished the daily tasks.');

        $row = $this->service->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-08-04 10:00:00'));

        $this->assertSame('2026-08-04', $row->date->toDateString());
    }

    public function test_clock_in_sets_planned_hours_and_day_flags(): void
    {
        // 2026-08-07 is a Friday — also marked as a rest date.
        $employee = User::factory()->employee()->create([
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
        ]);
        RestDay::factory()->create([
            'user_id' => $employee->id,
            'date' => '2026-08-07',
        ]);
        OfficialHoliday::create([
            'date' => '2026-08-07', 'name_ar' => 'تجربة', 'name_en' => 'Test',
        ]);

        $row = $this->service->clockIn(
            $employee,
            Attendance::SHIFT_FULL_TIME,
            Carbon::parse('2026-08-07 10:00:00'),
        );

        $this->assertSame('8.00', $row->planned_hours);
        $this->assertTrue($row->is_friday_work);
        $this->assertTrue($row->is_day_off_work);
        $this->assertTrue($row->is_official_holiday_work);
        $this->assertSame('2026-08-07', $row->date->toDateString());
        $this->assertNull($row->clock_out);
    }

    public function test_overnight_clock_out_uses_real_next_day_datetime(): void
    {
        $employee = User::factory()->employee()->create([
            'work_start_time' => '22:00',
            'work_end_time' => '06:00', // overnight — real next-day clock_out
        ]);

        $this->service->clockIn(
            $employee,
            Attendance::SHIFT_FULL_TIME,
            Carbon::parse('2026-08-02 22:00:00'),
        );

        $row = $this->service->clockOut(
            $employee,
            Carbon::parse('2026-08-03 06:00:00'),
        );

        $this->assertSame('2026-08-02', $row->date->toDateString());
        $this->assertSame('2026-08-03 06:00:00', $row->clock_out->format('Y-m-d H:i:s'));
        $this->assertSame('8.00', $row->actual_hours);
        $this->assertSame(0, $row->late_minutes);
        $this->assertSame(0, $row->early_departure_minutes);
        $this->assertSame(Attendance::STATUS_PRESENT, $row->status);
        $this->assertSame('100.00', $row->attendance_score);
    }

    public function test_late_and_early_departure_minutes(): void
    {
        $employee = User::factory()->employee()->create([
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
        ]);

        $this->service->clockIn(
            $employee,
            Attendance::SHIFT_FULL_TIME,
            Carbon::parse('2026-08-03 10:15:00'),
        );

        $row = $this->service->clockOut(
            $employee,
            Carbon::parse('2026-08-03 17:40:00'),
        );

        $this->assertSame(15, $row->late_minutes);
        $this->assertSame(20, $row->early_departure_minutes);
        $this->assertSame(Attendance::STATUS_LATE, $row->status);
        $this->assertSame('7.42', $row->actual_hours); // 7h25m
        $this->assertFalse((bool) $row->is_auto_clock_out);
    }

    public function test_auto_clock_out_after_grace_marks_automatic_and_uses_end_plus_grace(): void
    {
        config(['attendance.auto_clock_out_grace_minutes' => 60]);

        $leader = User::factory()->teamLeader()->create();
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee()->create([
            'team_leader_id' => $leader->id,
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
        ]);

        $this->service->clockIn(
            $employee,
            Attendance::SHIFT_FULL_TIME,
            Carbon::parse('2026-08-03 10:00:00'),
        );

        $this->assertSame(0, $this->service->autoClockOutOverdue(Carbon::parse('2026-08-03 18:30:00')));
        $this->assertNull(Attendance::where('user_id', $employee->id)->value('clock_out'));

        $closed = $this->service->autoClockOutOverdue(Carbon::parse('2026-08-03 19:05:00'));
        $this->assertSame(1, $closed);

        $row = Attendance::where('user_id', $employee->id)->firstOrFail();
        $this->assertTrue((bool) $row->is_auto_clock_out);
        $this->assertSame('2026-08-03 19:00:00', $row->clock_out->format('Y-m-d H:i:s'));
        $this->assertSame('9.00', $row->actual_hours);
        $this->assertSame(0, $row->early_departure_minutes);

        $this->assertSame(1, $employee->fresh()->unreadNotifications()->count());
        $this->assertSame(1, $leader->fresh()->unreadNotifications()->count());
        $this->assertSame(1, $manager->fresh()->unreadNotifications()->count());
        $this->assertSame(
            'attendance_auto_clock_out',
            $employee->notifications()->first()->data['kind'],
        );
    }

    public function test_online_half_expected_end_follows_shift_window_not_profile(): void
    {
        config(['attendance.auto_clock_out_grace_minutes' => 60]);

        // Profile is FT-style 10–18; Online ½ Excel window is 06–10 (4h).
        $employee = User::factory()->employee()->create([
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
            'shift_type_default' => Attendance::SHIFT_ONLINE_HALF,
        ]);

        $this->service->clockIn(
            $employee,
            Attendance::SHIFT_ONLINE_HALF,
            Carbon::parse('2026-08-03 06:00:00'),
        );

        $end = $this->service->expectedEndFor(
            $employee,
            Attendance::SHIFT_ONLINE_HALF,
            Carbon::parse('2026-08-03 06:00:00'),
        );
        $this->assertSame('10:00', $end?->format('H:i'));

        // Still in grace at 10:30 — no close.
        $this->assertSame(0, $this->service->autoClockOutOverdue(Carbon::parse('2026-08-03 10:30:00')));

        // Force at 11:00 (end 10:00 + 60m), not 19:00 from profile.
        $this->assertSame(1, $this->service->autoClockOutOverdue(Carbon::parse('2026-08-03 11:05:00')));
        $row = Attendance::where('user_id', $employee->id)->firstOrFail();
        $this->assertSame('2026-08-03 11:00:00', $row->clock_out->format('Y-m-d H:i:s'));
        $this->assertTrue((bool) $row->is_auto_clock_out);
        $this->assertSame('5.00', $row->actual_hours); // 06:00 → 11:00
    }

    public function test_late_minutes_are_set_on_clock_in(): void
    {
        $employee = User::factory()->employee()->create([
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
        ]);

        $row = $this->service->clockIn(
            $employee,
            Attendance::SHIFT_FULL_TIME,
            Carbon::parse('2026-08-03 10:20:00'),
        );

        $this->assertSame(20, $row->late_minutes);
        $this->assertSame(Attendance::STATUS_LATE, $row->status);
        $this->assertSame(
            '10:00',
            $this->service->expectedStartFor($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-08-03 10:20:00'))?->format('H:i'),
        );
        $this->assertSame(
            20,
            $this->service->previewLateMinutes($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-08-03 10:20:00')),
        );
    }

    public function test_attendance_score_caps_at_planned_ratio(): void
    {
        $employee = User::factory()->employee()->create([
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
        ]);

        $this->service->clockIn(
            $employee,
            Attendance::SHIFT_FULL_TIME,
            Carbon::parse('2026-08-03 10:00:00'),
        );

        $row = $this->service->clockOut(
            $employee,
            Carbon::parse('2026-08-03 14:00:00'), // 4h of 8h planned = 50%
        );

        $this->assertSame('4.00', $row->actual_hours);
        $this->assertSame('50.00', $row->attendance_score);
    }

    public function test_second_open_clock_in_is_rejected(): void
    {
        $employee = User::factory()->employee()->create();

        $this->service->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-08-03 06:00'));

        $this->expectException(AttendanceException::class);
        $this->service->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-08-03 07:00'));
    }

    public function test_extra_shift_requires_scheduled_assignment(): void
    {
        $employee = User::factory()->employee()->create();

        $this->expectException(AttendanceException::class);
        $this->service->clockIn($employee, Attendance::SHIFT_OVERTIME, Carbon::parse('2026-08-03 18:00'));
    }

    public function test_overtime_uses_assignment_expected_hours(): void
    {
        $leader = User::factory()->teamLeader()->create();
        $employee = User::factory()->employee()->create(['team_leader_id' => $leader->id]);

        ShiftAssignment::factory()->create([
            'user_id' => $employee->id,
            'assigned_by_tl_id' => $leader->id,
            'date' => '2026-08-03',
            'shift_type' => ShiftAssignment::TYPE_OVERTIME,
            'expected_start_time' => '18:00',
            'expected_end_time' => '22:00',
            'expected_hours' => 4,
            'status' => ShiftAssignment::STATUS_SCHEDULED,
        ]);

        $in = $this->service->clockIn(
            $employee,
            Attendance::SHIFT_OVERTIME,
            Carbon::parse('2026-08-03 18:00:00'),
        );
        $this->assertSame('4.00', $in->planned_hours);
        $this->assertSame($leader->id, $in->assigned_by_tl_id);

        $out = $this->service->clockOut(
            $employee,
            Carbon::parse('2026-08-03 22:00:00'),
        );
        $this->assertSame('4.00', $out->actual_hours);
        $this->assertSame(
            ShiftAssignment::STATUS_COMPLETED,
            ShiftAssignment::query()->where('user_id', $employee->id)->value('status'),
        );
    }

    public function test_online_full_planned_hours_is_twelve(): void
    {
        $employee = User::factory()->employee()->create([
            'work_start_time' => '10:00',
            'work_end_time' => '22:00',
            'shift_type_default' => Attendance::SHIFT_ONLINE_FULL,
        ]);

        $row = $this->service->clockIn(
            $employee,
            Attendance::SHIFT_ONLINE_FULL,
            Carbon::parse('2026-08-03 10:00:00'),
        );

        $this->assertSame('12.00', $row->planned_hours);
    }

    public function test_clock_out_without_open_record_fails(): void
    {
        $employee = User::factory()->employee()->create();

        $this->expectException(AttendanceException::class);
        $this->service->clockOut($employee);
    }

    public function test_preferred_shift_uses_tl_assignment_then_default(): void
    {
        $leader = User::factory()->teamLeader()->create();
        $employee = User::factory()->employee()->create([
            'team_leader_id' => $leader->id,
            'shift_type_default' => Attendance::SHIFT_ONLINE_HALF,
        ]);

        $this->assertSame(
            Attendance::SHIFT_ONLINE_HALF,
            $this->service->preferredShiftType($employee, Carbon::parse('2026-08-03')),
        );

        ShiftAssignment::factory()->fullTime()->create([
            'user_id' => $employee->id,
            'assigned_by_tl_id' => $leader->id,
            'date' => '2026-08-03',
        ]);

        $this->assertSame(
            Attendance::SHIFT_FULL_TIME,
            $this->service->preferredShiftType($employee, Carbon::parse('2026-08-03')),
        );
    }

    public function test_employee_cannot_clock_online_when_ft_is_planned(): void
    {
        $leader = User::factory()->teamLeader()->create();
        $employee = User::factory()->employee()->create(['team_leader_id' => $leader->id]);

        ShiftAssignment::factory()->fullTime()->create([
            'user_id' => $employee->id,
            'assigned_by_tl_id' => $leader->id,
            'date' => '2026-08-03',
            'expected_hours' => 8,
        ]);

        $this->expectException(AttendanceException::class);
        $this->expectExceptionMessage('clock.errors.plan_mismatch');

        $this->service->clockIn(
            $employee,
            Attendance::SHIFT_ONLINE_HALF,
            Carbon::parse('2026-08-03 10:00:00'),
        );
    }

    public function test_employee_may_clock_planned_ft_and_assigned_overtime(): void
    {
        $leader = User::factory()->teamLeader()->create();
        $employee = User::factory()->employee()->create(['team_leader_id' => $leader->id]);

        ShiftAssignment::factory()->fullTime()->create([
            'user_id' => $employee->id,
            'assigned_by_tl_id' => $leader->id,
            'date' => '2026-08-03',
            'expected_hours' => 8,
        ]);
        ShiftAssignment::factory()->create([
            'user_id' => $employee->id,
            'assigned_by_tl_id' => $leader->id,
            'date' => '2026-08-03',
            'shift_type' => Attendance::SHIFT_OVERTIME,
            'expected_hours' => 2,
        ]);

        $available = $this->service->availableShiftTypes($employee, Carbon::parse('2026-08-03'));
        $this->assertSame(
            [Attendance::SHIFT_FULL_TIME, Attendance::SHIFT_OVERTIME],
            $available,
        );

        $row = $this->service->clockIn(
            $employee,
            Attendance::SHIFT_FULL_TIME,
            Carbon::parse('2026-08-03 10:00:00'),
        );
        $this->assertSame(Attendance::SHIFT_FULL_TIME, $row->shift_type);
    }

    public function test_same_shift_type_twice_same_day_is_rejected(): void
    {
        $employee = User::factory()->employee()->create();

        $this->service->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-08-03 10:00'));
        $this->service->clockOut($employee, Carbon::parse('2026-08-03 18:00'));

        $this->expectException(AttendanceException::class);
        $this->service->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-08-03 19:00'));
    }

    public function test_used_shift_types_are_excluded_from_available(): void
    {
        $employee = User::factory()->employee()->create([
            'shift_type_default' => Attendance::SHIFT_FULL_TIME,
        ]);

        $this->service->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-08-03 10:00'));
        $this->service->clockOut($employee, Carbon::parse('2026-08-03 18:00'));

        $available = $this->service->availableShiftTypes($employee, Carbon::parse('2026-08-03'));

        $this->assertSame([], $available);
    }

    public function test_matching_base_assignment_uses_expected_hours(): void
    {
        $leader = User::factory()->teamLeader()->create();
        $employee = User::factory()->employee()->create(['team_leader_id' => $leader->id]);

        ShiftAssignment::factory()->onlineHalf()->create([
            'user_id' => $employee->id,
            'assigned_by_tl_id' => $leader->id,
            'date' => '2026-08-03',
            'expected_hours' => 3.5,
        ]);

        $row = $this->service->clockIn(
            $employee,
            Attendance::SHIFT_ONLINE_HALF,
            Carbon::parse('2026-08-03 10:00:00'),
        );

        $this->assertSame('3.50', $row->planned_hours);
        $this->assertSame($leader->id, $row->assigned_by_tl_id);
    }
}
