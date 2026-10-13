<?php

namespace Tests\Unit\Attendance;

use App\Models\Attendance;
use App\Models\PayrollRecord;
use App\Models\User;
use App\Services\Attendance\AttendanceHistoryService;
use App\Services\Attendance\AttendanceService;
use App\Services\Attendance\AttendanceTimeCalculator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Task 1.6 — grace effective 2026-10-01; protect pre-October stored/derived values.
 */
class AttendanceGraceEffectiveDateTest extends TestCase
{
    use RefreshDatabase;

    public function test_september_summarize_uses_stored_late_not_new_grace(): void
    {
        $employee = User::factory()->employee()->create([
            'work_start_time' => '09:00',
            'work_end_time' => '17:00',
        ]);

        $attendance = Attendance::factory()->create([
            'user_id' => $employee->id,
            'date' => '2026-09-30',
            'shift_type' => Attendance::SHIFT_FULL_TIME,
            'clock_in' => '2026-09-30 09:10:00',
            'clock_out' => '2026-09-30 16:50:00',
            'planned_hours' => 8,
            'raw_actual_hours' => 7.67,
            'actual_hours' => 7.67,
            'late_minutes' => 10,
            'early_departure_minutes' => 10,
            'status' => Attendance::STATUS_LATE,
        ]);

        $service = app(AttendanceService::class);
        $summary = app(AttendanceTimeCalculator::class)->summarize($attendance, $service);

        $this->assertSame(10, $summary->systemLateMinutes);
        $this->assertSame(10, $summary->systemEarlyMinutes);
        $this->assertSame(10, $service->exactLateMinutes($attendance));
        $this->assertFalse($summary->durationBalance['grace_policy_active'] ?? true);
    }

    public function test_recalculate_does_not_rewrite_september_derived_fields(): void
    {
        $employee = User::factory()->employee()->create([
            'work_start_time' => '09:00',
            'work_end_time' => '17:00',
        ]);

        $attendance = Attendance::factory()->create([
            'user_id' => $employee->id,
            'date' => '2026-09-15',
            'shift_type' => Attendance::SHIFT_FULL_TIME,
            'clock_in' => '2026-09-15 09:10:00',
            'clock_out' => '2026-09-15 16:50:00',
            'planned_hours' => 8,
            'raw_actual_hours' => 7.67,
            'actual_hours' => 7.67,
            'late_minutes' => 10,
            'early_departure_minutes' => 10,
            'status' => Attendance::STATUS_LATE,
        ]);

        // Attach segments so recalculate can run hours math.
        $attendance->segments()->create([
            'sequence' => 1,
            'clock_in' => '2026-09-15 09:10:00',
            'clock_out' => '2026-09-15 16:50:00',
        ]);

        $fresh = app(AttendanceTimeCalculator::class)->recalculate(
            $attendance->fresh(['segments', 'user']),
            app(AttendanceService::class),
        );

        $this->assertSame(10, (int) $fresh->late_minutes);
        $this->assertSame(10, (int) $fresh->early_departure_minutes);
        $this->assertSame(Attendance::STATUS_LATE, $fresh->status);
    }

    public function test_october_first_applies_grace_on_clock_out(): void
    {
        $employee = User::factory()->employee()->create([
            'work_start_time' => '09:00',
            'work_end_time' => '17:00',
        ]);

        $service = app(AttendanceService::class);
        $service->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-10-01 09:10'));
        $attendance = $service->clockOut($employee, Carbon::parse('2026-10-01 16:50'));

        $this->assertSame(0, (int) $attendance->late_minutes);
        $this->assertSame(0, (int) $attendance->early_departure_minutes);

        $summary = app(AttendanceTimeCalculator::class)->summarize($attendance->fresh(['segments', 'user']), $service);
        $this->assertSame(0, $summary->undertimeMinutes());
        $this->assertSame('balanced', $summary->hoursBalanceClassification());
    }

    public function test_october_sixteen_minute_late_counts_full(): void
    {
        $employee = User::factory()->employee()->create([
            'work_start_time' => '09:00',
            'work_end_time' => '17:00',
        ]);

        $service = app(AttendanceService::class);
        $service->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-10-02 09:16'));
        $attendance = $service->clockOut($employee, Carbon::parse('2026-10-02 17:00'));

        $this->assertSame(16, (int) $attendance->late_minutes);
        $this->assertSame(16, $service->exactLateMinutes($attendance));
    }

    public function test_september_history_kind_stays_legacy_shortfall_not_grace_balanced(): void
    {
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee()->create([
            'work_start_time' => '09:00',
            'work_end_time' => '17:00',
        ]);
        Attendance::factory()->create([
            'user_id' => $employee->id,
            'date' => '2026-09-30',
            'shift_type' => Attendance::SHIFT_FULL_TIME,
            'clock_in' => '2026-09-30 09:10:00',
            'clock_out' => '2026-09-30 16:50:00',
            'actual_hours' => 7.67,
            'raw_actual_hours' => 7.67,
            'planned_hours' => 8,
            'late_minutes' => 10,
            'early_departure_minutes' => 10,
        ]);

        $page = app(AttendanceHistoryService::class)->paginate($manager, [
            'employee_id' => $employee->id,
            'from' => '2026-09-30',
            'to' => '2026-09-30',
            'kind' => 'all',
            'evidence' => 'all',
            'page' => 1,
            'path' => '/',
        ]);

        $this->assertSame('shortfall', $page->items()[0]['kind']);
    }

    public function test_approved_payroll_cannot_refresh_from_attendance(): void
    {
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee()->create();
        $payroll = PayrollRecord::factory()->create([
            'user_id' => $employee->id,
            'created_by' => $manager->id,
            'month' => 9,
            'year' => 2026,
            'status' => PayrollRecord::STATUS_APPROVED,
            'late_minutes_total' => 42,
            'net_pay' => 3000,
        ]);

        $this->actingAs($manager)
            ->post(route('admin.payroll.refresh-attendance', $payroll))
            ->assertForbidden(); // settled APPROVED must not refresh silently

        $this->assertSame(42, (int) $payroll->fresh()->late_minutes_total);
        $this->assertSame('3000.00', (string) $payroll->fresh()->net_pay);
    }

    public function test_paid_payroll_cannot_be_edited(): void
    {
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee()->create();
        $payroll = PayrollRecord::factory()->create([
            'user_id' => $employee->id,
            'created_by' => $manager->id,
            'month' => 9,
            'year' => 2026,
            'status' => PayrollRecord::STATUS_PAID,
            'late_minutes_total' => 42,
        ]);

        $this->actingAs($manager)
            ->put(route('admin.payroll.update', $payroll), [
                'full_time_days' => 20,
                'online_half_days' => 0,
                'online_full_days' => 0,
                'rest_days' => 4,
                'day_off_work_days' => 0,
                'official_holiday_work_days' => 0,
                'late_minutes_total' => 0,
                'excused_absence_days' => 0,
                'unexcused_absence_days' => 0,
                'hours_deduction' => 0,
                'extra_days' => 0,
                'extra_pay' => 0,
                'overtime_hours' => 0,
                'overtime_multiplier' => 1.5,
            ])
            ->assertForbidden();

        $this->assertSame(42, (int) $payroll->fresh()->late_minutes_total);
    }
}
