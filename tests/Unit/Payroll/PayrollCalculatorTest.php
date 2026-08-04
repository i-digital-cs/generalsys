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
            onlineHalfDayRate: 50, // friday online uses half-day rate override
            onlineFullDayRate: 100,
        ));

        $this->assertSame(150.0, $result->fridayBonus); // FT 1×100 + online half-rate 50×1
        $this->assertSame(350.0, $result->dayOffBonus); // FT 2×100 + online 100×1.5
    }

    public function test_online_rates_use_employee_fields_with_fallback(): void
    {
        $fallback = $this->calculator->calculate(new PayrollCalculationInput(
            baseSalary: 6000,
            currency: 'EGP',
            onlineHalfDays: 2,
            onlineFullDays: 1,
        ));

        $this->assertSame(200.0, $fallback->onlineHalfPay); // (6000/30/2)×2
        $this->assertSame(200.0, $fallback->onlineFullPay); // (6000/30)×1

        $custom = $this->calculator->calculate(new PayrollCalculationInput(
            baseSalary: 6000,
            currency: 'EGP',
            onlineHalfDays: 2,
            onlineFullDays: 1,
            onlineHalfDayRate: 150,
            onlineFullDayRate: 250,
        ));

        $this->assertSame(300.0, $custom->onlineHalfPay);
        $this->assertSame(250.0, $custom->onlineFullPay);
    }
}
