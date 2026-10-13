<?php

namespace Tests\Unit\Attendance;

use App\Models\Attendance;
use App\Models\User;
use App\Services\Attendance\AttendanceHistoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceHistoryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_day_punch_is_present_not_absent(): void
    {
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee()->create([
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
        ]);
        Attendance::factory()->create([
            'user_id' => $employee->id,
            'date' => '2026-08-10',
            'shift_type' => Attendance::SHIFT_FULL_TIME,
            'clock_in' => '2026-08-10 10:00:00',
            'clock_out' => '2026-08-10 18:00:00',
            'actual_hours' => 8,
            'raw_actual_hours' => 8,
            'planned_hours' => 8,
            'late_minutes' => 0,
            'early_departure_minutes' => 0,
        ]);

        $page = app(AttendanceHistoryService::class)->paginate($manager, [
            'employee_id' => $employee->id,
            'from' => '2026-08-10',
            'to' => '2026-08-10',
            'kind' => 'all',
            'evidence' => 'all',
            'page' => 1,
            'path' => '/',
        ]);

        $this->assertSame(1, $page->total());
        $this->assertSame('present', $page->items()[0]['kind']);
        $this->assertSame(0.0, $page->items()[0]['absent_hours']);
    }

    public function test_no_punch_is_absent(): void
    {
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee()->create([
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
        ]);

        $page = app(AttendanceHistoryService::class)->paginate($manager, [
            'employee_id' => $employee->id,
            'from' => '2026-08-10',
            'to' => '2026-08-10',
            'kind' => 'absent',
            'evidence' => 'all',
            'page' => 1,
            'path' => '/',
        ]);

        $this->assertSame(1, $page->total());
        $this->assertSame('absent', $page->items()[0]['kind']);
    }

    public function test_grace_within_arrival_and_departure_is_present_not_shortfall(): void
    {
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee()->create([
            'work_start_time' => '09:00',
            'work_end_time' => '17:00',
        ]);
        Attendance::factory()->create([
            'user_id' => $employee->id,
            'date' => '2026-10-10',
            'shift_type' => Attendance::SHIFT_FULL_TIME,
            'clock_in' => '2026-10-10 09:10:00',
            'clock_out' => '2026-10-10 16:50:00',
            'actual_hours' => 7.67,
            'raw_actual_hours' => 7.67,
            'planned_hours' => 8,
            'late_minutes' => 0,
            'early_departure_minutes' => 0,
        ]);

        $page = app(AttendanceHistoryService::class)->paginate($manager, [
            'employee_id' => $employee->id,
            'from' => '2026-10-10',
            'to' => '2026-10-10',
            'kind' => 'all',
            'evidence' => 'all',
            'page' => 1,
            'path' => '/',
        ]);

        $this->assertSame(1, $page->total());
        $this->assertSame('present', $page->items()[0]['kind']);
    }

    public function test_surplus_hours_are_surplus_not_shortfall(): void
    {
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee()->create([
            'work_start_time' => '09:00',
            'work_end_time' => '17:00',
        ]);
        Attendance::factory()->create([
            'user_id' => $employee->id,
            'date' => '2026-10-10',
            'shift_type' => Attendance::SHIFT_FULL_TIME,
            'clock_in' => '2026-10-10 08:45:00',
            'clock_out' => '2026-10-10 17:30:00',
            'actual_hours' => 8,
            'raw_actual_hours' => 8.75,
            'planned_hours' => 8,
            'credited_hours' => 8,
        ]);

        $page = app(AttendanceHistoryService::class)->paginate($manager, [
            'employee_id' => $employee->id,
            'from' => '2026-10-10',
            'to' => '2026-10-10',
            'kind' => 'all',
            'evidence' => 'all',
            'page' => 1,
            'path' => '/',
        ]);

        $this->assertSame(1, $page->total());
        $this->assertSame('surplus', $page->items()[0]['kind']);
    }

    public function test_partial_day_punch_is_shortfall_not_absent(): void
    {
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee()->create([
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
        ]);
        Attendance::factory()->create([
            'user_id' => $employee->id,
            'date' => '2026-08-10',
            'shift_type' => Attendance::SHIFT_FULL_TIME,
            'clock_in' => '2026-08-10 10:00:00',
            'clock_out' => '2026-08-10 14:00:00',
            'actual_hours' => 4,
            'raw_actual_hours' => 4,
            'planned_hours' => 8,
        ]);

        $page = app(AttendanceHistoryService::class)->paginate($manager, [
            'employee_id' => $employee->id,
            'from' => '2026-08-10',
            'to' => '2026-08-10',
            'kind' => 'all',
            'evidence' => 'all',
            'page' => 1,
            'path' => '/',
        ]);

        $this->assertSame(1, $page->total());
        $this->assertSame('shortfall', $page->items()[0]['kind']);
        $this->assertSame(4.0, $page->items()[0]['absent_hours']);
    }

    public function test_open_punch_is_shortfall_not_absent(): void
    {
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee()->create([
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
        ]);
        Attendance::factory()->create([
            'user_id' => $employee->id,
            'date' => '2026-08-10',
            'shift_type' => Attendance::SHIFT_FULL_TIME,
            'clock_in' => '2026-08-10 10:00:00',
            'clock_out' => null,
            'actual_hours' => 0,
            'planned_hours' => 8,
        ]);

        $page = app(AttendanceHistoryService::class)->paginate($manager, [
            'employee_id' => $employee->id,
            'from' => '2026-08-10',
            'to' => '2026-08-10',
            'kind' => 'all',
            'evidence' => 'all',
            'page' => 1,
            'path' => '/',
        ]);

        $this->assertSame(1, $page->total());
        $this->assertSame('shortfall', $page->items()[0]['kind']);
    }
}
