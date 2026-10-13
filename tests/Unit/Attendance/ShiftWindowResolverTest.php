<?php

namespace Tests\Unit\Attendance;

use App\Models\Attendance;
use App\Models\User;
use App\Services\Attendance\AttendanceService;
use App\Services\Attendance\ShiftWindowResolver;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Characterization — expected windows match AttendanceService facade after Phase 2 extract.
 */
class ShiftWindowResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_eight_hour_window_keeps_end(): void
    {
        $user = User::factory()->employee()->create([
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
        ]);
        $resolver = app(ShiftWindowResolver::class);

        $start = $resolver->expectedStartFor($user, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-10-03 12:00'));
        $end = $resolver->expectedEndFor($user, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-10-03 12:00'));

        $this->assertSame('2026-10-03 10:00:00', $start?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-03 18:00:00', $end?->format('Y-m-d H:i:s'));
    }

    public function test_true_overnight_window_crosses_midnight(): void
    {
        $user = User::factory()->employee()->create([
            'work_start_time' => '22:00',
            'work_end_time' => '06:00',
            'shift_types_default' => [
                ['type' => Attendance::SHIFT_FULL_TIME, 'start' => '22:00', 'end' => '06:00'],
            ],
        ]);
        $resolver = app(ShiftWindowResolver::class);

        $end = $resolver->expectedEndFor($user, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-10-03 23:00'));

        $this->assertSame('2026-10-04 06:00:00', $end?->format('Y-m-d H:i:s'));
    }

    public function test_facade_matches_attendance_service_expected_end(): void
    {
        $user = User::factory()->employee()->create([
            'work_start_time' => '22:00',
            'work_end_time' => '10:00',
            'shift_types_default' => [
                ['type' => Attendance::SHIFT_FULL_TIME, 'start' => '22:00', 'end' => '10:00'],
            ],
        ]);
        $at = Carbon::parse('2026-10-02 22:00:00');
        $viaService = app(AttendanceService::class)
            ->expectedEndFor($user, Attendance::SHIFT_FULL_TIME, $at);
        $viaResolver = app(ShiftWindowResolver::class)
            ->expectedEndFor($user, Attendance::SHIFT_FULL_TIME, $at);

        $this->assertNotNull($viaService);
        $this->assertSame($viaService->format('Y-m-d H:i:s'), $viaResolver?->format('Y-m-d H:i:s'));
    }
}
