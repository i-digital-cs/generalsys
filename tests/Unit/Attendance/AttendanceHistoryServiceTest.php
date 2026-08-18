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
        $this->assertSame('present', $page->items()[0]['kind']);
        $this->assertSame(0.0, $page->items()[0]['absent_hours']);
    }
}
