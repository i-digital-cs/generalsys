<?php

namespace Tests\Unit\Payroll;

use App\Models\Attendance;
use App\Models\PayrollRecord;
use App\Models\User;
use App\Services\Attendance\AttendanceService;
use App\Services\Payroll\PayrollAggregator;
use App\Services\Payroll\PayrollDraftService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Financial integrity: payroll suggestions use grace-counted SSOT, not punch raw / stale columns.
 */
class PayrollGraceFinancialIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private AttendanceService $attendance;

    private PayrollAggregator $aggregator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->attendance = app(AttendanceService::class);
        $this->aggregator = app(PayrollAggregator::class);
    }

    /** @return list<array{0: int, 1: int}> minutes late → suggested */
    public static function lateSuggestionProvider(): array
    {
        return [
            [14, 0],
            [15, 0],
            [16, 16],
            [17, 17],
        ];
    }

    /** @return list<array{0: int, 1: int}> */
    public static function earlySuggestionProvider(): array
    {
        return [
            [14, 0],
            [15, 0],
            [16, 16],
            [17, 17],
        ];
    }

    #[DataProvider('lateSuggestionProvider')]
    public function test_suggested_late_matches_counted_not_raw(int $rawLate, int $suggested): void
    {
        $employee = User::factory()->employee()->create([
            'work_start_time' => '09:00',
            'work_end_time' => '17:00',
        ]);

        $this->attendance->clockIn(
            $employee,
            Attendance::SHIFT_FULL_TIME,
            Carbon::parse('2026-10-20 09:00')->addMinutes($rawLate)
        );
        $this->attendance->clockOut($employee, Carbon::parse('2026-10-20 17:00'));

        $live = $this->aggregator->aggregate($employee, 10, 2026);

        $this->assertSame($suggested, (int) $live['suggested_late_minutes_total']);
        $this->assertSame($suggested, (int) $live['late_minutes_total']);
        $this->assertSame($rawLate, (int) $live['raw_late_minutes_total']);
        $this->assertSame(0, (int) $live['suggested_early_departure_minutes_total']);
    }

    #[DataProvider('earlySuggestionProvider')]
    public function test_suggested_early_matches_counted(int $rawEarly, int $suggested): void
    {
        $employee = User::factory()->employee()->create([
            'work_start_time' => '09:00',
            'work_end_time' => '17:00',
        ]);

        $this->attendance->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-10-21 09:00'));
        $this->attendance->clockOut(
            $employee,
            Carbon::parse('2026-10-21 17:00')->subMinutes($rawEarly)
        );

        $live = $this->aggregator->aggregate($employee, 10, 2026);

        $this->assertSame($suggested, (int) $live['suggested_early_departure_minutes_total']);
        $this->assertSame(0, (int) $live['suggested_late_minutes_total']);
    }

    public function test_combined_late_and_early_suggestions(): void
    {
        $employee = User::factory()->employee()->create([
            'work_start_time' => '09:00',
            'work_end_time' => '17:00',
        ]);

        $this->attendance->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-10-22 09:10'));
        $this->attendance->clockOut($employee, Carbon::parse('2026-10-22 16:44'));

        $live = $this->aggregator->aggregate($employee, 10, 2026);
        $this->assertSame(0, (int) $live['suggested_late_minutes_total']); // 10 ≤ 15
        $this->assertSame(16, (int) $live['suggested_early_departure_minutes_total']);
        $this->assertSame(10, (int) $live['raw_late_minutes_total']);
    }

    public function test_overnight_shift_suggested_late_uses_grace(): void
    {
        $employee = User::factory()->employee()->create([
            'work_start_time' => '21:00',
            'work_end_time' => '09:00',
        ]);

        $this->attendance->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-10-23 21:10'));
        $this->attendance->clockOut($employee, Carbon::parse('2026-10-24 09:00'));

        $live = $this->aggregator->aggregate($employee, 10, 2026);
        $this->assertSame(0, (int) $live['suggested_late_minutes_total']);
        $this->assertSame(10, (int) $live['raw_late_minutes_total']);
    }

    public function test_multi_segment_suggested_late_from_first_in(): void
    {
        $employee = User::factory()->employee()->create([
            'work_start_time' => '09:00',
            'work_end_time' => '17:00',
        ]);

        $this->attendance->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-10-24 09:16'));
        $this->attendance->clockOut($employee, Carbon::parse('2026-10-24 12:00'));
        $this->attendance->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-10-24 13:00'));
        $this->attendance->clockOut($employee, Carbon::parse('2026-10-24 17:00'));

        $live = $this->aggregator->aggregate($employee, 10, 2026);
        $this->assertSame(16, (int) $live['suggested_late_minutes_total']);
    }

    public function test_stale_persisted_late_zero_still_suggests_exact(): void
    {
        $employee = User::factory()->employee()->create([
            'work_start_time' => '09:00',
            'work_end_time' => '17:00',
        ]);

        // Factory-style stale row (stored 0 / PRESENT) like TEST 1046–1055.
        Attendance::factory()->create([
            'user_id' => $employee->id,
            'date' => '2026-10-25',
            'shift_type' => Attendance::SHIFT_FULL_TIME,
            'clock_in' => '2026-10-25 09:20:00',
            'clock_out' => '2026-10-25 17:00:00',
            'planned_hours' => 8,
            'raw_actual_hours' => 7.67,
            'actual_hours' => 7.67,
            'late_minutes' => 0,
            'status' => Attendance::STATUS_PRESENT,
        ]);

        $live = $this->aggregator->aggregate($employee, 10, 2026);
        $this->assertSame(20, (int) $live['suggested_late_minutes_total']);
        $this->assertSame(20, (int) $live['late_minutes_total']);
        $this->assertSame(20, (int) $live['raw_late_minutes_total']);
    }

    public function test_manual_hr_override_preserved_on_save_not_silently_overwritten(): void
    {
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee()->create([
            'work_start_time' => '09:00',
            'work_end_time' => '17:00',
            'base_salary' => 3000,
        ]);

        $this->attendance->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-10-26 09:16'));
        $this->attendance->clockOut($employee, Carbon::parse('2026-10-26 17:00'));

        $payroll = PayrollRecord::factory()->create([
            'user_id' => $employee->id,
            'created_by' => $manager->id,
            'month' => 10,
            'year' => 2026,
            'status' => PayrollRecord::STATUS_DRAFT,
            'late_minutes_total' => 99,
            'base_salary' => 3000,
        ]);

        $updated = app(PayrollDraftService::class)->updateCounts($payroll, [
            'full_time_days' => 1,
            'online_half_days' => 0,
            'online_full_days' => 0,
            'rest_days' => 0,
            'day_off_work_days' => 0,
            'official_holiday_work_days' => 0,
            'late_minutes_total' => 99,
            'excused_absence_days' => 0,
            'unexcused_absence_days' => 0,
            'hours_deduction' => 0,
            'extra_days' => 0,
            'extra_pay' => 0,
            'overtime_hours' => 0,
            'overtime_multiplier' => 1.5,
        ]);

        $this->assertSame(99, (int) $updated->late_minutes_total);
    }

    public function test_apply_suggested_late_sets_exact_from_server_not_client(): void
    {
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee()->create([
            'work_start_time' => '09:00',
            'work_end_time' => '17:00',
            'base_salary' => 3000,
        ]);

        $this->attendance->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-10-27 09:10'));
        $this->attendance->clockOut($employee, Carbon::parse('2026-10-27 17:00'));

        $payroll = PayrollRecord::factory()->create([
            'user_id' => $employee->id,
            'created_by' => $manager->id,
            'month' => 10,
            'year' => 2026,
            'status' => PayrollRecord::STATUS_DRAFT,
            'late_minutes_total' => 999,
            'base_salary' => 3000,
        ]);

        $applied = app(PayrollDraftService::class)->applySuggestedLateMinutes($payroll);

        $this->assertSame(0, (int) $applied->late_minutes_total);
    }

    public function test_apply_suggested_late_http_ignores_forged_payload(): void
    {
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee()->create([
            'work_start_time' => '09:00',
            'work_end_time' => '17:00',
            'base_salary' => 3000,
        ]);

        $this->attendance->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-10-28 09:16'));
        $this->attendance->clockOut($employee, Carbon::parse('2026-10-28 17:00'));

        $payroll = PayrollRecord::factory()->create([
            'user_id' => $employee->id,
            'created_by' => $manager->id,
            'month' => 10,
            'year' => 2026,
            'status' => PayrollRecord::STATUS_DRAFT,
            'late_minutes_total' => 0,
            'base_salary' => 3000,
        ]);

        $this->actingAs($manager)
            ->post(route('admin.payroll.apply-suggested-late', $payroll), [
                'late_minutes_total' => 10, // forged raw — must be ignored
            ])
            ->assertRedirect(route('admin.payroll.show', $payroll));

        $this->assertSame(16, (int) $payroll->fresh()->late_minutes_total);
    }

    public function test_approved_payroll_cannot_apply_suggested_late(): void
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
        ]);

        $this->actingAs($manager)
            ->post(route('admin.payroll.apply-suggested-late', $payroll))
            ->assertForbidden();

        $this->assertSame(42, (int) $payroll->fresh()->late_minutes_total);
    }

    public function test_pre_october_suggested_uses_stored_not_new_grace(): void
    {
        $employee = User::factory()->employee()->create([
            'work_start_time' => '09:00',
            'work_end_time' => '17:00',
        ]);

        Attendance::factory()->create([
            'user_id' => $employee->id,
            'date' => '2026-09-15',
            'shift_type' => Attendance::SHIFT_FULL_TIME,
            'clock_in' => '2026-09-15 09:10:00',
            'clock_out' => '2026-09-15 17:00:00',
            'planned_hours' => 8,
            'actual_hours' => 7.83,
            'late_minutes' => 10,
            'status' => Attendance::STATUS_LATE,
        ]);

        $live = $this->aggregator->aggregate($employee, 9, 2026);
        $this->assertSame(10, (int) $live['suggested_late_minutes_total']);
        $this->assertSame(10, (int) $live['late_minutes_total']);
    }

    public function test_draft_show_renders_policy_suggestion_not_raw_ten(): void
    {
        app()->setLocale('en');
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee()->create([
            'work_start_time' => '09:00',
            'work_end_time' => '17:00',
            'base_salary' => 3000,
        ]);

        $this->attendance->clockIn($employee, Attendance::SHIFT_FULL_TIME, Carbon::parse('2026-10-29 09:10'));
        $this->attendance->clockOut($employee, Carbon::parse('2026-10-29 17:00'));

        $payroll = PayrollRecord::factory()->create([
            'user_id' => $employee->id,
            'created_by' => $manager->id,
            'month' => 10,
            'year' => 2026,
            'status' => PayrollRecord::STATUS_DRAFT,
            'late_minutes_total' => 5,
            'base_salary' => 3000,
        ]);

        $response = $this->actingAs($manager)
            ->get(route('admin.payroll.show', $payroll))
            ->assertOk()
            ->assertViewHas('suggestedLateMinutes', 0)
            ->assertViewHas('suggestedEarlyMinutes', 0)
            ->assertSee(__('payroll.field_help.late_minutes_apply_suggested'), false)
            ->assertSee('hp-apply-suggested-late-'.$payroll->id, false)
            ->assertDontSee('data-apply-suggested-late=', false);

        // Suggested help embeds counted 0, not raw punch 10.
        $this->assertStringContainsString('Suggested from attendance policy (grace-adjusted): 0 min', $response->getContent());
        $this->assertStringNotContainsString('Suggested from attendance policy (grace-adjusted): 10 min', $response->getContent());
    }
}
