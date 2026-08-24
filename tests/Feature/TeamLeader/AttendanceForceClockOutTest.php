<?php

namespace Tests\Feature\TeamLeader;

use App\Models\Attendance;
use App\Models\User;
use App\Notifications\AttendanceForceClockOutNotification;
use App\Services\Attendance\AttendanceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Tests\TestCase;

/**
 * T-7.11 — TL/Admin/Assistant end an open shift at actual time.
 */
class AttendanceForceClockOutTest extends TestCase
{
    use RefreshDatabase;

    private User $leader;

    private User $otherLeader;

    private User $manager;

    private User $assistant;

    private User $employee;

    private User $outsider;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('en');

        $this->leader = User::factory()->teamLeader()->create();
        $this->otherLeader = User::factory()->teamLeader()->create();
        $this->manager = User::factory()->manager()->create();
        $this->assistant = User::factory()->assistant()->create();
        $this->employee = User::factory()->employee()->create([
            'team_leader_id' => $this->leader->id,
            'name_en' => 'Late Member',
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
        ]);
        $this->outsider = User::factory()->employee()->create([
            'team_leader_id' => $this->otherLeader->id,
            'name_en' => 'Other Team',
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function openShift(User $employee, string $in = '2026-08-24 10:00'): Attendance
    {
        return app(AttendanceService::class)->clockIn(
            $employee,
            Attendance::SHIFT_FULL_TIME,
            Carbon::parse($in),
        );
    }

    public function test_team_leader_ends_open_shift_at_actual_time(): void
    {
        NotificationFacade::fake();
        Carbon::setTestNow(Carbon::parse('2026-08-24 19:30'));

        $attendance = $this->openShift($this->employee);

        $this->actingAs($this->leader)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('clock.end_shift.button'), false);

        $this->actingAs($this->leader)
            ->from(route('dashboard'))
            ->post(route('team-leader.attendance.force-clock-out', $attendance))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('success');

        $fresh = $attendance->fresh();
        $this->assertNotNull($fresh->clock_out);
        $this->assertSame('19:30', $fresh->clock_out->format('H:i'));
        $this->assertSame($this->leader->id, $fresh->force_closed_by);
        $this->assertFalse((bool) $fresh->is_auto_clock_out);
        $this->assertGreaterThan((float) $fresh->planned_hours, (float) $fresh->raw_actual_hours);

        NotificationFacade::assertSentTo($this->employee, AttendanceForceClockOutNotification::class);
        NotificationFacade::assertSentTo($this->manager, AttendanceForceClockOutNotification::class);
        NotificationFacade::assertNotSentTo($this->leader, AttendanceForceClockOutNotification::class);
    }

    public function test_manager_and_assistant_can_end_any_open_shift(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-24 18:45'));
        $attendance = $this->openShift($this->outsider);

        $this->actingAs($this->manager)
            ->post(route('team-leader.attendance.force-clock-out', $attendance))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame($this->manager->id, $attendance->fresh()->force_closed_by);

        $second = $this->openShift($this->employee, '2026-08-24 10:05');
        $this->actingAs($this->assistant)
            ->post(route('team-leader.attendance.force-clock-out', $second))
            ->assertSessionHas('success');

        $this->assertSame($this->assistant->id, $second->fresh()->force_closed_by);
    }

    public function test_team_leader_cannot_end_other_team_shift(): void
    {
        $attendance = $this->openShift($this->outsider);

        $this->actingAs($this->leader)
            ->post(route('team-leader.attendance.force-clock-out', $attendance))
            ->assertForbidden();

        $this->assertNull($attendance->fresh()->clock_out);
    }

    public function test_employee_cannot_end_own_shift_via_supervisor_route(): void
    {
        $attendance = $this->openShift($this->employee);

        $this->actingAs($this->employee)
            ->post(route('team-leader.attendance.force-clock-out', $attendance))
            ->assertForbidden();
    }

    public function test_already_closed_shift_cannot_be_ended_again(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-24 18:00'));
        $service = app(AttendanceService::class);
        $service->clockIn($this->employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-08-24 10:00'));
        $closed = $service->clockOut($this->employee, Carbon::parse('2026-08-24 18:00'));

        $this->actingAs($this->leader)
            ->from(route('dashboard'))
            ->post(route('team-leader.attendance.force-clock-out', $closed))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error');
    }
}
