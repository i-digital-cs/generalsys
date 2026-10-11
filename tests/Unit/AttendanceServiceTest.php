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

        $this->assertSame(0, $row->late_minutes);
        $this->assertSame(20, $row->early_departure_minutes);
        $this->assertSame(Attendance::STATUS_EARLY_LEAVE, $row->status);
        $this->assertSame('7.42', $row->actual_hours); // 7h25m
        $this->assertFalse((bool) $row->is_auto_clock_out);
    }

    public function test_auto_clock_out_is_disabled(): void
    {
        $leader = User::factory()->teamLeader()->create();
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

        $this->assertSame(0, $this->service->autoClockOutOverdue(Carbon::parse('2026-08-03 19:05:00')));
        $this->assertNull(Attendance::where('user_id', $employee->id)->value('clock_out'));
        $this->assertFalse((bool) Attendance::where('user_id', $employee->id)->value('is_auto_clock_out'));
    }

    public function test_online_half_expected_end_follows_shift_window_not_profile(): void
    {
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

        // Auto clock-out disabled — punch stays open past end + grace.
        $this->assertSame(0, $this->service->autoClockOutOverdue(Carbon::parse('2026-08-03 11:05:00')));
        $this->assertNull(Attendance::where('user_id', $employee->id)->value('clock_out'));
    }

    public function test_flexible_start_never_auto_marks_late_on_clock_in(): void
    {
        $employee = User::factory()->employee()->create([
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
        ]);

        $at = Carbon::parse('2026-08-03 10:20:00');
        $this->assertSame(0, $this->service->previewLateMinutes($employee, Attendance::SHIFT_FULL_TIME, $at));

        $row = $this->service->clockIn(
            $employee,
            Attendance::SHIFT_FULL_TIME,
            $at,
        );

        $this->assertSame(0, $row->late_minutes);
        $this->assertSame(Attendance::STATUS_PRESENT, $row->status);
        $this->assertSame(
            '10:00',
            $this->service->expectedStartFor($employee, Attendance::SHIFT_FULL_TIME, $at)?->format('H:i'),
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

        $this->service->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-08-03 10:00'));

        $this->expectException(AttendanceException::class);
        $this->service->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-08-03 10:30'));
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
            ShiftAssignment::STATUS_SCHEDULED,
            ShiftAssignment::query()->where('user_id', $employee->id)->value('status'),
        );
    }

    public function test_online_full_planned_hours_is_twelve(): void
    {
        $leader = User::factory()->teamLeader()->create();
        $employee = User::factory()->employee()->create([
            'team_leader_id' => $leader->id,
            'work_start_time' => '10:00',
            'work_end_time' => '22:00',
            'shift_type_default' => Attendance::SHIFT_FULL_TIME,
        ]);

        ShiftAssignment::factory()->create([
            'user_id' => $employee->id,
            'assigned_by_tl_id' => $leader->id,
            'date' => '2026-08-03',
            'shift_type' => Attendance::SHIFT_ONLINE_FULL,
            'expected_hours' => 12,
            'expected_start_time' => '10:00',
            'expected_end_time' => '22:00',
            'status' => ShiftAssignment::STATUS_SCHEDULED,
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

        $available = $this->service->availableShiftTypes($employee, Carbon::parse('2026-08-03 10:00:00'));
        $this->assertContains(Attendance::SHIFT_FULL_TIME, $available);
        $this->assertContains(Attendance::SHIFT_OVERTIME, $available);
        $this->assertSame(Attendance::SHIFT_FULL_TIME, $this->service->preferredShiftType($employee, Carbon::parse('2026-08-03 10:00:00')));
        $this->assertContains(
            Attendance::SHIFT_OVERTIME,
            $this->service->availableShiftTypes($employee, Carbon::parse('2026-08-03 18:00:00')),
        );

        $row = $this->service->clockIn(
            $employee,
            Attendance::SHIFT_FULL_TIME,
            Carbon::parse('2026-08-03 10:00:00'),
        );
        $this->assertSame(Attendance::SHIFT_FULL_TIME, $row->shift_type);
    }

    public function test_same_shift_type_can_resume_after_clock_out(): void
    {
        $employee = User::factory()->employee()->create();

        $this->service->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-08-03 10:00'));
        $this->service->clockOut($employee, Carbon::parse('2026-08-03 11:00'));

        $row = $this->service->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-08-03 12:00'));
        $this->service->clockOut($employee, Carbon::parse('2026-08-03 18:00'));

        $this->assertSame(1, Attendance::where('user_id', $employee->id)->count());
        $this->assertSame(2, $row->segments()->count());
        $this->assertDatabaseHas('attendance_gaps', [
            'attendance_id' => $row->id,
            'minutes' => 60,
        ]);
        $this->assertSame('7.00', $row->fresh()->actual_hours);
    }

    public function test_used_shift_types_remain_available_for_resume(): void
    {
        $employee = User::factory()->employee()->create([
            'shift_type_default' => Attendance::SHIFT_FULL_TIME,
        ]);

        $this->service->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-08-03 10:00'));
        $this->service->clockOut($employee, Carbon::parse('2026-08-03 18:00'));

        $available = $this->service->availableShiftTypes($employee, Carbon::parse('2026-08-03'));

        $this->assertContains(Attendance::SHIFT_FULL_TIME, $available);
    }

    public function test_employee_profile_window_defines_planned_hours_for_early_leave(): void
    {
        $employee = User::factory()->employee()->create([
            'work_start_time' => '09:00',
            'work_end_time' => '17:00',
            'shift_types_default' => [
                ['type' => Attendance::SHIFT_FULL_TIME, 'start' => '09:00', 'end' => '17:00'],
            ],
        ]);

        $end = $this->service->expectedEndFor(
            $employee,
            Attendance::SHIFT_FULL_TIME,
            Carbon::parse('2026-08-03 09:00:00'),
        );
        $this->assertSame('2026-08-03 17:00:00', $end?->format('Y-m-d H:i:s'));

        $this->service->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-08-03 09:01:00'));
        $row = $this->service->clockOut($employee, Carbon::parse('2026-08-03 16:18:00'));

        $this->assertSame('8.00', $row->planned_hours);
        $this->assertSame(0, $row->late_minutes);
        $this->assertSame(42, $row->early_departure_minutes);
        $this->assertSame('7.28', $row->actual_hours);
        $this->assertNull($row->credited_hours);
    }

    public function test_true_overnight_eight_hour_window_keeps_next_day_end(): void
    {
        $employee = User::factory()->employee()->create([
            'work_start_time' => '22:00',
            'work_end_time' => '06:00',
            'shift_types_default' => [
                ['type' => Attendance::SHIFT_FULL_TIME, 'start' => '22:00', 'end' => '06:00'],
            ],
        ]);

        $end = $this->service->expectedEndFor(
            $employee,
            Attendance::SHIFT_FULL_TIME,
            Carbon::parse('2026-08-02 22:00:00'),
        );
        $this->assertSame('2026-08-03 06:00:00', $end?->format('Y-m-d H:i:s'));
    }

    public function test_wide_overnight_profile_uses_full_window_as_planned_hours(): void
    {
        $employee = User::factory()->employee()->create([
            'work_start_time' => '22:00',
            'work_end_time' => '10:00',
            'shift_types_default' => [
                ['type' => Attendance::SHIFT_FULL_TIME, 'start' => '22:00', 'end' => '10:00'],
            ],
        ]);

        $this->assertSame(12.0, $employee->plannedHoursFor(Attendance::SHIFT_FULL_TIME));

        $end = $this->service->expectedEndFor(
            $employee,
            Attendance::SHIFT_FULL_TIME,
            Carbon::parse('2026-08-02 22:00:00'),
        );
        $this->assertSame('2026-08-03 10:00:00', $end?->format('Y-m-d H:i:s'));

        $this->service->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-08-02 22:00:00'));
        $row = Attendance::query()->where('user_id', $employee->id)->latest('id')->first();
        $this->assertSame('12.00', $row->planned_hours);
    }

    public function test_online_full_sixteen_hour_overnight_profile_counts_full_window(): void
    {
        $employee = User::factory()->employee()->create([
            'work_start_time' => '09:00',
            'work_end_time' => '01:00',
            'shift_type_default' => Attendance::SHIFT_ONLINE_FULL,
            'shift_types_default' => [
                ['type' => Attendance::SHIFT_ONLINE_FULL, 'start' => '09:00', 'end' => '01:00'],
            ],
        ]);

        $this->assertSame(16.0, $employee->plannedHoursFor(Attendance::SHIFT_ONLINE_FULL));

        $end = $this->service->expectedEndFor(
            $employee,
            Attendance::SHIFT_ONLINE_FULL,
            Carbon::parse('2026-09-15 09:00:00'),
        );
        $this->assertSame('2026-09-16 01:00:00', $end?->format('Y-m-d H:i:s'));

        $this->service->clockIn($employee, Attendance::SHIFT_ONLINE_FULL, Carbon::parse('2026-09-15 09:00:00'));
        $row = Attendance::query()->where('user_id', $employee->id)->latest('id')->first();
        $this->assertSame('16.00', $row->planned_hours);

        $out = $this->service->clockOut($employee, Carbon::parse('2026-09-16 01:00:00'));
        $this->assertSame('16.00', $out->raw_actual_hours);
        $this->assertSame('16.00', $out->actual_hours);
        $this->assertSame(0, (int) $out->early_departure_minutes);
    }

    public function test_preview_late_is_zero_when_resuming_same_shift(): void
    {
        $employee = User::factory()->employee()->create([
            'work_start_time' => '09:00',
            'work_end_time' => '17:00',
        ]);

        $this->service->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-08-03 09:00'));
        $this->service->clockOut($employee, Carbon::parse('2026-08-03 16:00'));

        $this->assertSame(
            0,
            $this->service->previewLateMinutes($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-08-03 16:20')),
        );
    }

    public function test_stale_cross_day_open_is_force_closed_within_one_hour_of_shift_start(): void
    {
        $employee = User::factory()->employee()->create([
            'work_start_time' => '09:00',
            'work_end_time' => '17:00',
        ]);

        $this->service->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-08-22 09:00'));
        $this->assertNull(Attendance::where('user_id', $employee->id)->value('clock_out'));

        $closed = $this->service->closeStaleOpenBeforeShiftStart(
            $employee,
            Carbon::parse('2026-08-23 08:30:00'),
            Attendance::SHIFT_FULL_TIME,
        );

        $this->assertNotNull($closed);
        $this->assertTrue((bool) $closed->is_auto_clock_out);
        $this->assertSame('2026-08-22', $closed->date->toDateString());
        $this->assertSame('2026-08-22 17:00:00', $closed->clock_out->format('Y-m-d H:i:s'));
        $this->assertNull($this->service->openAttendance($employee));

        app(DailyReportService::class)
            ->submit($employee, '2026-08-22', 'Closed automatically after forgotten punch.');

        $row = $this->service->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-08-23 08:30:00'));
        $this->assertSame('2026-08-23', $row->date->toDateString());
    }

    public function test_stale_close_for_night_punch_with_day_profile_credits_elapsed_not_one_minute(): void
    {
        // Profile 00:00–12:00 but employee clocks in at night (real driver pattern).
        $employee = User::factory()->employee()->create([
            'work_start_time' => '00:00',
            'work_end_time' => '12:00',
            'shift_types_default' => [[
                'type' => Attendance::SHIFT_FULL_TIME,
                'start' => '00:00',
                'end' => '12:00',
                'expected_hours' => 12,
            ]],
        ]);

        $this->service->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-10-06 23:02:00'));

        $closed = $this->service->closeStaleOpenBeforeShiftStart(
            $employee,
            Carbon::parse('2026-10-07 11:01:00'),
            Attendance::SHIFT_FULL_TIME,
        );

        $this->assertNotNull($closed);
        $this->assertTrue((bool) $closed->is_auto_clock_out);
        $this->assertSame('2026-10-07 11:01:00', $closed->clock_out->format('Y-m-d H:i:s'));
        $this->assertGreaterThan(10.0, (float) $closed->raw_actual_hours);
        $this->assertEqualsWithDelta(
            min(12.0, (float) $closed->raw_actual_hours),
            (float) $closed->actual_hours,
            0.01,
        );
    }

    public function test_stale_cross_day_open_not_closed_more_than_one_hour_before_shift(): void
    {
        $employee = User::factory()->employee()->create([
            'work_start_time' => '09:00',
            'work_end_time' => '17:00',
        ]);

        $this->service->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-08-22 09:00'));

        $this->assertNull($this->service->closeStaleOpenBeforeShiftStart(
            $employee,
            Carbon::parse('2026-08-23 07:30:00'),
            Attendance::SHIFT_FULL_TIME,
        ));
        $this->assertNotNull($this->service->openAttendance($employee));
    }

    public function test_same_day_open_is_not_force_closed(): void
    {
        $employee = User::factory()->employee()->create([
            'work_start_time' => '09:00',
            'work_end_time' => '17:00',
        ]);

        $this->service->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-08-23 09:00'));

        $this->assertNull($this->service->closeStaleOpenBeforeShiftStart(
            $employee,
            Carbon::parse('2026-08-23 16:00:00'),
            Attendance::SHIFT_FULL_TIME,
        ));
        $this->assertNotNull($this->service->openAttendance($employee));
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
