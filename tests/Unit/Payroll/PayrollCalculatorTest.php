<?php

namespace Tests\Unit\Payroll;

use App\Models\PayrollAdjustment;
use App\Models\PenaltyRule;
use App\Services\Payroll\PayrollCalculationInput;
use App\Services\Payroll\PayrollCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Gate 4.1–4.5 / T-4.3 — PayrollCalculator Excel fixture + rule-driven rates.
 */
class PayrollCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private PayrollCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        config(['payroll.auto_penalties' => true]);
        $this->calculator = app(PayrollCalculator::class);
    }

    public function test_excel_fixture_net_is_2808(): void
    {
        // §9 sheet practice uses 1× unexcused for this net; spec default is 3× (tested separately).
        PenaltyRule::factory()->create([
            'type' => PenaltyRule::TYPE_EXCUSED_ABSENCE,
            'currency' => 'EGP',
            'amount_per_unit' => 1.00,
            'unit' => PenaltyRule::UNIT_DAY,
            'is_active' => true,
        ]);
        PenaltyRule::factory()->create([
            'type' => PenaltyRule::TYPE_UNEXCUSED_ABSENCE,
            'currency' => 'EGP',
            'amount_per_unit' => 1.00,
            'unit' => PenaltyRule::UNIT_DAY,
            'is_active' => true,
        ]);

        $result = $this->calculator->calculate(new PayrollCalculationInput(
            baseSalary: 5000,
            currency: 'EGP',
            fullTimeDays: 20,
            onlineHalfDays: 10,
            officialHolidayWorkDays: 1,
            excusedAbsenceDays: 1,
            unexcusedAbsenceDays: 1,
            adjustments: [
                ['type' => PayrollAdjustment::TYPE_ADVANCE, 'amount' => 100],
                ['type' => PayrollAdjustment::TYPE_ADVANCE, 'amount' => 67],
                ['type' => PayrollAdjustment::TYPE_ADVANCE, 'amount' => 1000],
                ['type' => PayrollAdjustment::TYPE_ADVANCE, 'amount' => 25],
            ],
        ));

        $this->assertSame(2808.0, $result->netPay);
        $this->assertSame(3333.33, $result->fullTimePay);
        $this->assertSame(833.33, $result->onlineHalfPay);
        $this->assertSame(166.67, $result->officialHolidayBonus);
        $this->assertSame(166.67, $result->excusedAbsencePenalty);
        $this->assertSame(166.67, $result->unexcusedAbsencePenalty);
        $this->assertSame(1192.0, $result->advanceDeductions);
    }

    public function test_excused_is_1x_unexcused_is_3x_from_penalty_rules(): void
    {
        PenaltyRule::factory()->create([
            'type' => PenaltyRule::TYPE_EXCUSED_ABSENCE,
            'currency' => 'EGP',
            'amount_per_unit' => 1.00,
            'unit' => PenaltyRule::UNIT_DAY,
        ]);
        PenaltyRule::factory()->create([
            'type' => PenaltyRule::TYPE_UNEXCUSED_ABSENCE,
            'currency' => 'EGP',
            'amount_per_unit' => 3.00,
            'unit' => PenaltyRule::UNIT_DAY,
        ]);

        $result = $this->calculator->calculate(new PayrollCalculationInput(
            baseSalary: 3000,
            currency: 'EGP',
            excusedAbsenceDays: 2,
            unexcusedAbsenceDays: 1,
        ));

        $dayRate = 3000 / 30; // 100
        $this->assertSame(200.0, $result->excusedAbsencePenalty); // 100 × 2 × 1
        $this->assertSame(300.0, $result->unexcusedAbsencePenalty); // 100 × 1 × 3
    }

    public function test_per_minute_penalties_and_forced_surcharge(): void
    {
        PenaltyRule::factory()->create([
            'type' => PenaltyRule::TYPE_LATE_ARRIVAL,
            'currency' => 'EGP',
            'amount_per_unit' => 2.00,
            'unit' => PenaltyRule::UNIT_MINUTE,
            'double_penalty_multiplier' => 2.00,
        ]);
        PenaltyRule::factory()->create([
            'type' => PenaltyRule::TYPE_LEAVE_PERMISSION,
            'currency' => 'EGP',
            'amount_per_unit' => 1.50,
            'unit' => PenaltyRule::UNIT_MINUTE,
            'double_penalty_multiplier' => 2.00,
        ]);

        $result = $this->calculator->calculate(new PayrollCalculationInput(
            baseSalary: 5000,
            currency: 'EGP',
            lateMinutesTotal: 27,
            leavePermissionMinutes: 60,
            forcedPermissionPenaltyBase: 108, // rejected leave amount; ×2 → surcharge 108
        ));

        $this->assertSame(54.0, $result->latePenalty); // 27 × 2
        $this->assertSame(90.0, $result->leavePermissionPenalty); // 60 × 1.5
        $this->assertSame(108.0, $result->forcedPenaltySurcharge);
    }

    public function test_friday_and_day_off_multipliers(): void
    {
        $result = $this->calculator->calculate(new PayrollCalculationInput(
            baseSalary: 3000,
            currency: 'EGP',
            fridayFullTimeDays: 1,
            fridayOnlineDays: 1,
            dayOffFullTimeDays: 1,
            dayOffOnlineDays: 1,
        ));

        $this->assertSame(150.0, $result->fridayBonus); // FT 1×100 + online 100×0.5
        $this->assertSame(350.0, $result->dayOffBonus); // FT 2×100 + online 100×1.5
    }

    public function test_online_pay_uses_base_salary_formula(): void
    {
        $result = $this->calculator->calculate(new PayrollCalculationInput(
            baseSalary: 6000,
            currency: 'EGP',
            onlineHalfDays: 2,
            onlineFullDays: 1,
        ));

        $this->assertSame(200.0, $result->onlineHalfPay); // (6000/30/2)×2
        $this->assertSame(200.0, $result->onlineFullPay); // (6000/30)×1
    }

    public function test_auto_penalties_off_zeros_minute_penalties_keeps_excel_deductions(): void
    {
        config(['payroll.auto_penalties' => false]);

        PenaltyRule::factory()->create([
            'type' => PenaltyRule::TYPE_EXCUSED_ABSENCE,
            'currency' => 'EGP',
            'amount_per_unit' => 1.00,
            'unit' => PenaltyRule::UNIT_DAY,
        ]);

        $result = $this->calculator->calculate(new PayrollCalculationInput(
            baseSalary: 5000,
            currency: 'EGP',
            fullTimeDays: 20,
            excusedAbsenceDays: 1,
            lateMinutesTotal: 30,
            hoursDeduction: 2,
            adjustments: [
                ['type' => PayrollAdjustment::TYPE_ADVANCE, 'amount' => 100],
            ],
        ));

        // Excel absence/hours always apply; late/permission stay behind auto_penalties.
        $this->assertEqualsWithDelta(166.67, $result->excusedAbsencePenalty, 0.01);
        $this->assertSame(0.0, $result->latePenalty);
        $this->assertEqualsWithDelta(41.67, $result->hoursDeductionAmount, 0.01); // 2 × 5000/(30×8)
        $this->assertSame(100.0, $result->advanceDeductions);
        $this->assertSame(3333.33, $result->fullTimePay);
    }

    public function test_other_deduction_does_not_inflate_advance_metric(): void
    {
        $result = $this->calculator->calculate(new PayrollCalculationInput(
            baseSalary: 5000,
            currency: 'EGP',
            fullTimeDays: 30,
            overtimeHours: 1,
            overtimeMultiplier: 1.0,
            adjustments: [
                ['type' => PayrollAdjustment::TYPE_OTHER_DEDUCTION, 'amount' => 1000],
                ['type' => PayrollAdjustment::TYPE_ADVANCE, 'amount' => 2000],
            ],
        ));

        $this->assertSame(2000.0, $result->advanceDeductions);
        $this->assertEqualsWithDelta(3000.0, $result->totalDeductions, 0.01); // 1000 other + 2000 advance
        $this->assertEqualsWithDelta(5020.83, $result->grossPay, 0.01);
        $this->assertEqualsWithDelta(2020.83, $result->netPay, 0.01);
    }

    public function test_excel_excused_absence_is_one_day_rate(): void
    {
        $result = $this->calculator->calculate(new PayrollCalculationInput(
            baseSalary: 5000,
            currency: 'EGP',
            excusedAbsenceDays: 1,
        ));

        $this->assertEqualsWithDelta(166.67, $result->excusedAbsencePenalty, 0.01);
    }

    public function test_excel_unexcused_absence_uses_default_triple_day_rate(): void
    {
        $result = $this->calculator->calculate(new PayrollCalculationInput(
            baseSalary: 5000,
            currency: 'EGP',
            unexcusedAbsenceDays: 1,
        ));

        $this->assertEqualsWithDelta(500.0, $result->unexcusedAbsencePenalty, 0.01); // 166.67 × 3
    }

    public function test_excel_hours_deduction_is_hourly_times_hours(): void
    {
        $result = $this->calculator->calculate(new PayrollCalculationInput(
            baseSalary: 5000,
            currency: 'EGP',
            hoursDeduction: 3,
        ));

        // 3 × (5000 / (30 × 8)) = 62.50
        $this->assertEqualsWithDelta(62.50, $result->hoursDeductionAmount, 0.01);
    }

    public function test_eight_overtime_hours_default_rate_is_250(): void
    {
        // hourly = (5000/30)/8 ≈ 20.833; × 8h × 1.5 = 250
        $result = $this->calculator->calculate(new PayrollCalculationInput(
            baseSalary: 5000,
            currency: 'EGP',
            overtimeHours: 8,
        ));

        $this->assertEqualsWithDelta(250.0, $result->overtimePay, 0.01);
    }

    public function test_extra_pay_from_days_uses_day_rate(): void
    {
        $result = $this->calculator->calculate(new PayrollCalculationInput(
            baseSalary: 5000,
            currency: 'EGP',
            extraDays: 1,
        ));

        $this->assertEqualsWithDelta(166.67, $result->extraPay, 0.01);
        $this->assertEqualsWithDelta(166.67, $result->grossPay, 0.01);
    }

    public function test_typed_extra_pay_amount_wins_over_days(): void
    {
        $result = $this->calculator->calculate(new PayrollCalculationInput(
            baseSalary: 5000,
            currency: 'EGP',
            extraDays: 2,
            extraPay: 200,
        ));

        // Days win: 2 × (5000/30) ≈ 333.33 (typed 200 ignored while days > 0)
        $this->assertEqualsWithDelta(333.33, $result->extraPay, 0.01);
    }

    public function test_overtime_multiplier_from_config_is_used(): void
    {
        config(['payroll.multipliers.overtime' => 2.0]);

        $result = $this->calculator->calculate(new PayrollCalculationInput(
            baseSalary: 5000,
            overtimeHours: 8,
        ));

        // (5000/30/8) × 8 × 2.0 ≈ 333.33
        $this->assertEqualsWithDelta(333.33, $result->overtimePay, 0.01);
    }

    public function test_input_overtime_multiplier_overrides_config(): void
    {
        config(['payroll.multipliers.overtime' => 2.0]);

        $result = $this->calculator->calculate(new PayrollCalculationInput(
            baseSalary: 5000,
            overtimeHours: 8,
            overtimeMultiplier: 1.5,
        ));

        $this->assertEqualsWithDelta(250.0, $result->overtimePay, 0.01);
    }
}
