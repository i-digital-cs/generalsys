<?php

namespace Tests\Unit\Attendance;

use App\Models\Attendance;
use App\Models\PayrollRecord;
use App\Models\User;
use App\Services\Attendance\AttendanceCalculationEngine;
use App\Services\Attendance\AttendanceHistoryService;
use App\Services\Attendance\CheckoutProvenance;
use App\Services\Payroll\PayrollAggregator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Anonymized fixtures mirroring TEST att 680 (Reda fabricated) and 681 (Amir verified overnight).
 * Does not rewrite historical rows or settled payroll.
 */
class HistoricalPresentationIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_fabricated_auto_close_is_unverified_with_shift_review(): void
    {
        $user = User::factory()->employee()->create([
            'name_en' => 'Fixture Reda',
            'work_start_time' => '00:00',
            'work_end_time' => '12:00',
        ]);

        $row = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-10-20',
            'shift_type' => Attendance::SHIFT_FULL_TIME,
            'status' => Attendance::STATUS_LATE,
            'clock_in' => '2026-10-20 23:03:15',
            'clock_out' => '2026-10-21 11:03:15',
            'is_auto_clock_out' => true,
            'force_closed_by' => null,
            'late_minutes' => 1383,
            'early_departure_minutes' => 0,
            'planned_hours' => 12,
            'actual_hours' => 12,
            'raw_actual_hours' => 12,
        ]);
        $row->load(['segments', 'gaps', 'user']);

        $result = app(AttendanceCalculationEngine::class)->forAttendance($row);

        $this->assertSame(CheckoutProvenance::AUTO_STALE, $result->checkoutProvenance);
        $this->assertFalse($result->checkoutVerified());
        $this->assertFalse($result->durationVerified());
        $this->assertNull($result->verifiedWorkedHours());
        $this->assertSame(12.0, $result->recordedWorkedHours());
        $this->assertTrue($result->shiftReviewRequired());
        $this->assertFalse($result->lateDisplayTrustworthy());
        $this->assertSame(1383, $result->lateAgainstPlannedWindowMinutes());
    }

    public function test_verified_overnight_peer_is_trusted(): void
    {
        $user = User::factory()->employee()->create([
            'name_en' => 'Fixture Amir',
            'work_start_time' => '20:00',
            'work_end_time' => '08:00',
        ]);

        $row = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-10-20',
            'shift_type' => Attendance::SHIFT_FULL_TIME,
            'status' => Attendance::STATUS_LATE,
            'clock_in' => '2026-10-20 23:03:28',
            'clock_out' => '2026-10-21 11:45:31',
            'is_auto_clock_out' => false,
            'force_closed_by' => null,
            'late_minutes' => 183,
            'early_departure_minutes' => 0,
            'planned_hours' => 12,
            'actual_hours' => 12,
            'raw_actual_hours' => 12.70,
        ]);
        $row->load(['segments', 'gaps', 'user']);

        $result = app(AttendanceCalculationEngine::class)->forAttendance($row);

        $this->assertSame(CheckoutProvenance::EMPLOYEE, $result->checkoutProvenance);
        $this->assertTrue($result->durationVerified());
        $this->assertSame(12.7, $result->verifiedWorkedHours());
        $this->assertFalse($result->shiftReviewRequired());
        $this->assertTrue($result->lateDisplayTrustworthy());
        $this->assertSame(183, $result->countedLateMinutes());
    }

    public function test_missing_and_supervisor_checkout_semantics(): void
    {
        $user = User::factory()->employee()->create([
            'work_start_time' => '09:00',
            'work_end_time' => '17:00',
        ]);
        $leader = User::factory()->teamLeader()->create();

        $open = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-10-21',
            'shift_type' => Attendance::SHIFT_FULL_TIME,
            'clock_in' => '2026-10-21 09:00:00',
            'clock_out' => null,
            'actual_hours' => 0,
            'raw_actual_hours' => 0,
            'planned_hours' => 8,
        ]);
        $open->load(['segments', 'gaps', 'user']);
        $openResult = app(AttendanceCalculationEngine::class)->forAttendance($open);
        $this->assertSame(CheckoutProvenance::OPEN, $openResult->checkoutProvenance);
        $this->assertNull($openResult->verifiedWorkedHours());

        $forced = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-10-22',
            'shift_type' => Attendance::SHIFT_FULL_TIME,
            'clock_in' => '2026-10-22 09:00:00',
            'clock_out' => '2026-10-22 16:00:00',
            'force_closed_by' => $leader->id,
            'is_auto_clock_out' => false,
            'actual_hours' => 7,
            'raw_actual_hours' => 7,
            'planned_hours' => 8,
        ]);
        $forced->load(['segments', 'gaps', 'user']);
        $forcedResult = app(AttendanceCalculationEngine::class)->forAttendance($forced);
        $this->assertSame(CheckoutProvenance::SUPERVISOR, $forcedResult->checkoutProvenance);
        $this->assertFalse($forcedResult->durationVerified());
        $this->assertSame(7.0, $forcedResult->recordedWorkedHours());
    }

    public function test_history_row_flags_unverified_fabricated_session(): void
    {
        $manager = User::factory()->manager()->create();
        $user = User::factory()->employee()->create([
            'work_start_time' => '00:00',
            'work_end_time' => '12:00',
        ]);
        Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-10-20',
            'shift_type' => Attendance::SHIFT_FULL_TIME,
            'clock_in' => '2026-10-20 23:03:15',
            'clock_out' => '2026-10-21 11:03:15',
            'is_auto_clock_out' => true,
            'late_minutes' => 1383,
            'planned_hours' => 12,
            'actual_hours' => 12,
            'raw_actual_hours' => 12,
            'status' => Attendance::STATUS_LATE,
        ]);

        $page = app(AttendanceHistoryService::class)->paginate($manager, [
            'employee_id' => $user->id,
            'from' => '2026-10-20',
            'to' => '2026-10-20',
            'kind' => 'all',
            'evidence' => 'all',
            'page' => 1,
            'path' => '/',
        ]);

        $row = $page->items()[0];
        $this->assertTrue($row['has_unverified_duration']);
        $this->assertTrue($row['has_shift_review']);
        $this->assertSame(0.0, $row['verified_hours']);
        $this->assertSame(12.0, (float) $row['recorded_hours']);
        $this->assertSame(1383, (int) $row['late_minutes']);
        $this->assertSame(
            CheckoutProvenance::AUTO_STALE,
            $row['sessions'][0]['calculation']->checkoutProvenance,
        );
    }

    public function test_approved_payroll_snapshot_unchanged_when_live_late_differs(): void
    {
        $manager = User::factory()->manager()->create();
        $user = User::factory()->employee()->create([
            'work_start_time' => '00:00',
            'work_end_time' => '12:00',
        ]);
        Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-10-20',
            'shift_type' => Attendance::SHIFT_FULL_TIME,
            'clock_in' => '2026-10-20 23:03:15',
            'clock_out' => '2026-10-21 11:03:15',
            'is_auto_clock_out' => true,
            'late_minutes' => 1383,
            'status' => Attendance::STATUS_LATE,
            'planned_hours' => 12,
            'actual_hours' => 12,
            'raw_actual_hours' => 12,
        ]);

        $payroll = PayrollRecord::factory()->create([
            'user_id' => $user->id,
            'created_by' => $manager->id,
            'month' => 10,
            'year' => 2026,
            'status' => PayrollRecord::STATUS_APPROVED,
            'late_minutes_total' => 50,
        ]);

        $live = app(PayrollAggregator::class)->aggregate($user, 10, 2026);
        $this->assertSame(1383, (int) $live['late_minutes_total']);
        $this->assertSame(50, (int) $payroll->fresh()->late_minutes_total);
    }
}
