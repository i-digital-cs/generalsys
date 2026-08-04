<?php

namespace Tests\Feature;

use App\Models\PayrollAdjustment;
use App\Models\PayrollRecord;
use App\Models\PenaltyRule;
use App\Models\User;
use App\Services\Payroll\PayrollCalculationInput;
use App\Services\Payroll\PayrollCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T-5.6 / Gate 5.5 — re-run payroll fixture + TL payroll 403.
 */
class PayrollRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_excel_fixture_net_still_2808(): void
    {
        config(['payroll.auto_penalties' => true]);

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

        $result = app(PayrollCalculator::class)->calculate(new PayrollCalculationInput(
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
    }

    public function test_team_leader_still_blocked_from_payroll_and_exports(): void
    {
        $manager = User::factory()->manager()->create();
        $leader = User::factory()->teamLeader()->create();
        $payroll = PayrollRecord::factory()->create([
            'user_id' => User::factory()->employee()->create(['team_leader_id' => $leader->id])->id,
            'created_by' => $manager->id,
            'status' => PayrollRecord::STATUS_APPROVED,
        ]);

        $this->actingAs($leader);
        $this->get(route('admin.payroll.index'))->assertForbidden();
        $this->get(route('admin.payroll.show', $payroll))->assertForbidden();
        $this->get(route('admin.payroll.export.pdf', $payroll))->assertForbidden();
        $this->get(route('admin.payroll.export.xlsx', ['month' => 8, 'year' => 2026]))->assertForbidden();
        $this->get(route('admin.advances.index'))->assertForbidden();
        $this->get(route('admin.reports.annual'))->assertForbidden();
    }
}
