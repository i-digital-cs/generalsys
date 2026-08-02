<?php

namespace Tests\Unit;

use App\Exceptions\AttendanceException;
use App\Models\Attendance;
use App\Models\OfficialHoliday;
use App\Models\RestDay;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Services\Attendance\AttendanceService;
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

        $this->service = new AttendanceService(new ShiftConfig);
    }

    public function test_planned_hours_profiles(): void
    {
        $config = new ShiftConfig;

        $this->assertSame(8.0, $config->plannedHours(Attendance::SHIFT_FULL_TIME));
        $this->assertSame(4.0, $config->plannedHours(Attendance::SHIFT_ONLINE_HALF));
        $this->assertSame(12.0, $config->plannedHours(Attendance::SHIFT_ONLINE_FULL));
    }

    public function test_clock_in_sets_planned_hours_and_day_flags(): void
    {
        // 2026-08-07 is a Friday.
        $employee = User::factory()->employee()->create([
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
        ]);
        RestDay::create(['user_id' => $employee->id, 'date' => '2026-08-07', 'created_by' => $employee->id]);
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
    }

    public function test_second_open_clock_in_is_rejected(): void
    {
        $employee = User::factory()->employee()->create();

        $this->service->clockIn($employee, Attendance::SHIFT_ONLINE_HALF, Carbon::parse('2026-08-03 06:00'));

        $this->expectException(AttendanceException::class);
        $this->service->clockIn($employee, Attendance::SHIFT_ONLINE_HALF, Carbon::parse('2026-08-03 07:00'));
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
}
