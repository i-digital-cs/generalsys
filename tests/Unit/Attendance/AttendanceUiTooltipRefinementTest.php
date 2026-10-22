<?php

namespace Tests\Unit\Attendance;

use App\Models\Attendance;
use App\Models\User;
use App\Services\Attendance\AttendanceCalculationEngine;
use App\Services\Attendance\AttendanceHistoryService;
use App\Services\Attendance\AttendanceReviewPresentation;
use App\Services\Attendance\CheckoutProvenance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Presentation-only: one review indicator, dynamic tooltips, no SSOT math changes.
 */
class AttendanceUiTooltipRefinementTest extends TestCase
{
    use RefreshDatabase;

    public function test_review_reasons_and_narrative_are_dynamic_not_hardcoded_names(): void
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
            'late_minutes' => 1383,
            'planned_hours' => 12,
            'actual_hours' => 12,
            'raw_actual_hours' => 12,
        ]);
        $row->load(['segments', 'gaps', 'user']);

        $result = app(AttendanceCalculationEngine::class)->forAttendance($row);
        $codes = $result->reviewReasonCodes();

        $this->assertTrue($result->needsReview());
        $this->assertContains('SHIFT_WINDOW_MISMATCH', $codes);
        $this->assertContains('AUTO_CHECKOUT', $codes);
        $this->assertContains('UNVERIFIED_RECORDED_HOURS', $codes);
        $this->assertContains('CREDITED_POLICY_UNVERIFIED', $codes);
        $this->assertContains('LATE_DEPENDS_ON_UNCONFIRMED_SHIFT', $codes);

        $tooltip = app(AttendanceReviewPresentation::class)->sessionTooltip($result);
        $this->assertStringContainsString('00:00', $tooltip);
        $this->assertStringContainsString('12:00', $tooltip);
        $this->assertStringContainsString('23:03', $tooltip);
        $this->assertStringNotContainsString('Fixture Reda', $tooltip);
        $this->assertStringNotContainsString('680', $tooltip);
        $this->assertSame(1383, $result->lateAgainstPlannedWindowMinutes());
        $this->assertSame(CheckoutProvenance::AUTO_STALE, $result->checkoutProvenance);
    }

    public function test_history_exposes_single_review_payload_without_duplicate_reason_lists(): void
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

        $this->assertTrue($row['needs_review']);
        $this->assertNotEmpty($row['review_reason_codes']);
        $this->assertSame(
            count($row['review_reason_codes']),
            count(array_unique($row['review_reason_codes'])),
        );
        $this->assertNotSame('', $row['review_tooltip']);
        $this->assertSame(1383, (int) $row['late_minutes']);
    }

    public function test_review_indicator_blade_renders_once_with_tooltip(): void
    {
        $html = Blade::render(
            '<x-attendance-review-indicator :reason-codes="$codes" :tooltip="$tip" />',
            [
                'codes' => ['SHIFT_WINDOW_MISMATCH', 'AUTO_CHECKOUT'],
                'tip' => "Reason A\nReason B",
            ]
        );

        $this->assertSame(1, substr_count($html, __('attendance.review.indicator')));
        $this->assertStringContainsString('data-bs-toggle="tooltip"', $html);
        $this->assertStringContainsString('Reason A', $html);
        $this->assertStringNotContainsString(__('attendance.integrity.shift_review_required'), $html);
        $this->assertStringNotContainsString(__('attendance.integrity.credited_review_required'), $html);
    }

    public function test_english_and_arabic_review_strings_exist(): void
    {
        foreach (['en', 'ar'] as $locale) {
            app()->setLocale($locale);
            $this->assertNotSame('attendance.review.indicator', __('attendance.review.indicator'));
            $this->assertNotSame(
                'attendance.review.reasons.AUTO_CHECKOUT',
                __('attendance.review.reasons.AUTO_CHECKOUT'),
            );
            $this->assertStringContainsString(
                ':window_start',
                __('attendance.review.narrative.shift_auto_policy'),
            );
        }
    }

    public function test_ssot_late_unchanged_for_fabricated_fixture(): void
    {
        $user = User::factory()->employee()->create([
            'work_start_time' => '00:00',
            'work_end_time' => '12:00',
        ]);
        $row = Attendance::factory()->create([
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
        ]);
        $row->load(['segments', 'gaps', 'user']);

        $before = app(AttendanceCalculationEngine::class)->exactLateMinutes($row);
        $result = app(AttendanceCalculationEngine::class)->forAttendance($row);
        $this->assertSame($before, $result->countedLateMinutes());
        $this->assertSame(12.0, $result->creditedDisplayHours());
    }
}
