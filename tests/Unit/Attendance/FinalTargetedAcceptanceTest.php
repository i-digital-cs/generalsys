<?php

namespace Tests\Unit\Attendance;

use App\Models\Attendance;
use App\Models\User;
use App\Services\Admin\SettingService;
use App\Services\Attendance\AttendanceCalculationEngine;
use App\Services\Attendance\AttendanceHistoryService;
use App\Services\Attendance\CheckoutProvenance;
use App\Services\Attendance\GeofenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Targeted acceptance: credited provenance, punctuality ≠ presence, geo distance, OT eligible≠approved.
 */
class FinalTargetedAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_unverified_auto_close_credited_is_policy_cap_not_approval(): void
    {
        $user = User::factory()->employee()->create([
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
            'credited_hours' => null,
            'late_minutes' => 1383,
            'planned_hours' => 12,
            'actual_hours' => 12,
            'raw_actual_hours' => 12,
            'recommended_overtime_minutes' => 0,
            'overtime_tl_recommendation' => 'NONE',
            'overtime_admin_decision' => 'PENDING',
        ]);
        $row->load(['segments', 'gaps', 'user']);

        $result = app(AttendanceCalculationEngine::class)->forAttendance($row);

        $this->assertSame(CheckoutProvenance::AUTO_STALE, $result->checkoutProvenance);
        $this->assertSame('POLICY_CAP', $result->creditedProvenance());
        $this->assertTrue($result->creditedReviewRequired());
        $this->assertSame(12.0, $result->creditedDisplayHours());
        $this->assertNull($result->verifiedWorkedHours());
    }

    public function test_history_separates_attended_kind_from_late_punctuality(): void
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
            'status' => Attendance::STATUS_LATE,
            'clock_in' => '2026-10-20 23:03:15',
            'clock_out' => '2026-10-21 11:03:15',
            'is_auto_clock_out' => true,
            'late_minutes' => 1383,
            'planned_hours' => 12,
            'actual_hours' => 12,
            'raw_actual_hours' => 12,
        ]);

        $row = app(AttendanceHistoryService::class)->paginate($manager, [
            'employee_id' => $user->id,
            'from' => '2026-10-20',
            'to' => '2026-10-20',
            'kind' => 'all',
            'evidence' => 'all',
            'page' => 1,
            'path' => '/',
        ])->items()[0];

        $this->assertSame('late', $row['punctuality']);
        $this->assertNotSame('on_time', $row['punctuality']);
        $this->assertContains($row['kind'], ['present', 'shortfall', 'surplus']);
        $this->assertTrue($row['credited_review_required']);
        $this->assertContains('POLICY_CAP', $row['credited_provenance']);
    }

    public function test_overnight_surplus_is_eligible_not_approved_overtime(): void
    {
        $user = User::factory()->employee()->create([
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
            'planned_hours' => 12,
            'actual_hours' => 12,
            'raw_actual_hours' => 12.70,
            'late_minutes' => 183,
            'recommended_overtime_minutes' => 0,
            'overtime_tl_recommendation' => 'NONE',
            'overtime_admin_decision' => 'PENDING',
        ]);
        $row->load(['segments', 'gaps', 'user']);

        $result = app(AttendanceCalculationEngine::class)->forAttendance($row);

        $this->assertSame(12.7, $result->verifiedWorkedHours());
        $this->assertSame(12.0, $result->summary->plannedHours);
        $this->assertSame(42, $result->surplusMinutes());
        $this->assertSame(42, $result->overtimeEligibleMinutes());
        $this->assertSame(0, $result->approvedOvertimeMinutes());
    }

    public function test_geofence_distance_matches_haversine_cairo_office_to_dubai_punch(): void
    {
        $settings = app(SettingService::class);
        $settings->set(SettingService::KEY_OFFICE_LATITUDE, '30.1198431');
        $settings->set(SettingService::KEY_OFFICE_LONGITUDE, '31.3403046');
        $settings->set(SettingService::KEY_OFFICE_RADIUS_METERS, '100');

        $geo = app(GeofenceService::class);
        $office = $geo->office();
        $this->assertNotNull($office);
        $this->assertEqualsWithDelta(30.1198431, $office['lat'], 0.0001);
        $this->assertEqualsWithDelta(31.3403046, $office['lng'], 0.0001);

        // Dubai-area punch (anonymized 680/681 pattern).
        $distance = $geo->distanceMeters(25.2694281, 55.3301430);
        $this->assertNotNull($distance);
        $this->assertGreaterThan(2_400_000, $distance);
        $this->assertLessThan(2_450_000, $distance);
        $this->assertTrue($geo->evaluate(25.2694281, 55.3301430)['outside']);
    }
}
