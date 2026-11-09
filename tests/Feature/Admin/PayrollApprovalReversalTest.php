<?php

namespace Tests\Feature\Admin;

use App\Models\Advance;
use App\Models\PayrollAdjustment;
use App\Models\PayrollRecord;
use App\Models\PayrollStatusAudit;
use App\Models\User;
use App\Services\Payroll\PayrollApprovalReversalService;
use App\Services\Payroll\PayrollGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class PayrollApprovalReversalTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_does_not_mutate_approved_payroll(): void
    {
        [$employee, $payroll, $advance] = $this->approvedPayslipWithAdvance();

        $this->artisan('payroll:revert-approved-to-draft', [
            '--payroll-id' => $payroll->id,
        ])->assertSuccessful();

        $this->assertSame(PayrollRecord::STATUS_APPROVED, $payroll->fresh()->status);
        $this->assertSame(400.0, (float) $advance->fresh()->remaining_amount);
        $this->assertSame(0, PayrollStatusAudit::query()->count());
        $this->assertSame('2808.00', (string) $payroll->fresh()->net_pay);
    }

    public function test_execute_reverts_approved_to_draft_and_restores_advance(): void
    {
        [$employee, $payroll, $advance] = $this->approvedPayslipWithAdvance();
        $other = PayrollRecord::factory()->create([
            'user_id' => User::factory()->employee()->create()->id,
            'month' => 6,
            'year' => 2026,
            'status' => PayrollRecord::STATUS_APPROVED,
            'net_pay' => 9999,
            'created_by' => $payroll->created_by,
        ]);
        $net = (string) $payroll->net_pay;
        $gross = (string) $payroll->gross_pay;

        $this->artisan('payroll:revert-approved-to-draft', [
            '--payroll-id' => $payroll->id,
            '--execute' => true,
            '--reason' => 'Owner correction on TEST',
            '--actor-id' => $payroll->created_by,
        ])->assertSuccessful();

        $payroll->refresh();
        $advance->refresh();

        $this->assertSame(PayrollRecord::STATUS_DRAFT, $payroll->status);
        $this->assertSame($net, (string) $payroll->net_pay);
        $this->assertSame($gross, (string) $payroll->gross_pay);
        $this->assertSame(500.0, (float) $advance->remaining_amount);
        $this->assertSame(Advance::STATUS_ACTIVE, $advance->status);
        $this->assertSame(PayrollRecord::STATUS_APPROVED, $other->fresh()->status);
        $this->assertSame(1, PayrollStatusAudit::query()->count());

        $audit = PayrollStatusAudit::query()->first();
        $this->assertSame(PayrollRecord::STATUS_APPROVED, $audit->from_status);
        $this->assertSame(PayrollRecord::STATUS_DRAFT, $audit->to_status);
        $this->assertSame('Owner correction on TEST', $audit->reason);
    }

    public function test_ambiguous_arabic_name_is_blocked_and_lists_matches(): void
    {
        User::factory()->employee()->create(['name_ar' => 'مريم عبدالحليم']);
        User::factory()->employee()->create(['name_ar' => 'مريم عبد الحليم']);

        $this->artisan('payroll:revert-approved-to-draft', [
            '--name' => 'مريم عبدالحليم',
        ])->assertFailed();
    }

    public function test_normalized_arabic_name_selects_unique_employee(): void
    {
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee()->create(['name_ar' => 'مريم عبدالحليم']);
        $payroll = PayrollRecord::factory()->create([
            'user_id' => $employee->id,
            'month' => 5,
            'year' => 2026,
            'status' => PayrollRecord::STATUS_APPROVED,
            'created_by' => $manager->id,
            'net_pay' => 1500,
            'advance_deductions' => 0,
        ]);

        $this->artisan('payroll:revert-approved-to-draft', [
            '--name' => 'مريم عبد الحليم',
            '--execute' => true,
            '--reason' => 'Name search path',
        ])->assertSuccessful();

        $this->assertSame(PayrollRecord::STATUS_DRAFT, $payroll->fresh()->status);
    }

    public function test_multiple_approved_periods_require_payroll_id_or_month(): void
    {
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee()->create(['name_ar' => 'سارة أحمد']);
        PayrollRecord::factory()->create([
            'user_id' => $employee->id,
            'month' => 4,
            'year' => 2026,
            'status' => PayrollRecord::STATUS_APPROVED,
            'created_by' => $manager->id,
        ]);
        PayrollRecord::factory()->create([
            'user_id' => $employee->id,
            'month' => 5,
            'year' => 2026,
            'status' => PayrollRecord::STATUS_APPROVED,
            'created_by' => $manager->id,
        ]);

        $this->artisan('payroll:revert-approved-to-draft', [
            '--employee-id' => $employee->id,
        ])->assertFailed();

        $this->artisan('payroll:revert-approved-to-draft', [
            '--employee-id' => $employee->id,
            '--month' => 5,
            '--year' => 2026,
            '--execute' => true,
            '--reason' => 'Period select',
        ])->assertSuccessful();
    }

    public function test_paid_payroll_is_blocked(): void
    {
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee()->create();
        $payroll = PayrollRecord::factory()->create([
            'user_id' => $employee->id,
            'month' => 3,
            'year' => 2026,
            'status' => PayrollRecord::STATUS_PAID,
            'created_by' => $manager->id,
            'net_pay' => 2000,
        ]);

        $this->artisan('payroll:revert-approved-to-draft', [
            '--payroll-id' => $payroll->id,
            '--execute' => true,
            '--reason' => 'Should fail',
        ])->assertFailed();

        $this->assertSame(PayrollRecord::STATUS_PAID, $payroll->fresh()->status);
    }

    public function test_later_approved_payroll_blocks_earlier_reversal(): void
    {
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee()->create();
        $earlier = PayrollRecord::factory()->create([
            'user_id' => $employee->id,
            'month' => 4,
            'year' => 2026,
            'status' => PayrollRecord::STATUS_APPROVED,
            'created_by' => $manager->id,
        ]);
        PayrollRecord::factory()->create([
            'user_id' => $employee->id,
            'month' => 5,
            'year' => 2026,
            'status' => PayrollRecord::STATUS_APPROVED,
            'created_by' => $manager->id,
        ]);

        $this->artisan('payroll:revert-approved-to-draft', [
            '--payroll-id' => $earlier->id,
            '--execute' => true,
            '--reason' => 'Should fail chain',
        ])->assertFailed();

        $this->assertSame(PayrollRecord::STATUS_APPROVED, $earlier->fresh()->status);
    }

    public function test_repeated_execute_is_idempotent(): void
    {
        [, $payroll] = $this->approvedPayslipWithAdvance();

        $this->artisan('payroll:revert-approved-to-draft', [
            '--payroll-id' => $payroll->id,
            '--execute' => true,
            '--reason' => 'First',
        ])->assertSuccessful();

        $this->artisan('payroll:revert-approved-to-draft', [
            '--payroll-id' => $payroll->id,
            '--execute' => true,
            '--reason' => 'Second',
        ])->assertSuccessful();

        $this->assertSame(PayrollRecord::STATUS_DRAFT, $payroll->fresh()->status);
        $this->assertSame(1, PayrollStatusAudit::query()->count());
    }

    public function test_transaction_rolls_back_when_money_guard_trips(): void
    {
        [$employee, $payroll, $advance] = $this->approvedPayslipWithAdvance();
        $service = app(PayrollApprovalReversalService::class);

        try {
            DB::transaction(function () use ($service, $payroll) {
                $service->revert($payroll, true, 'Will abort', null);
                throw new InvalidArgumentException('forced rollback after revert');
            });
            $this->fail('Expected exception');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('forced rollback after revert', $e->getMessage());
        }

        $this->assertSame(PayrollRecord::STATUS_APPROVED, $payroll->fresh()->status);
        $this->assertSame(400.0, (float) $advance->fresh()->remaining_amount);
        $this->assertSame(0, PayrollStatusAudit::query()->count());
    }

    public function test_execute_requires_reason(): void
    {
        [, $payroll] = $this->approvedPayslipWithAdvance();

        $this->artisan('payroll:revert-approved-to-draft', [
            '--payroll-id' => $payroll->id,
            '--execute' => true,
        ])->assertFailed();
    }

    /**
     * @return array{0: User, 1: PayrollRecord, 2: Advance}
     */
    private function approvedPayslipWithAdvance(): array
    {
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee()->create(['base_salary' => 6000]);
        $advance = Advance::factory()->create([
            'user_id' => $employee->id,
            'amount' => 500,
            'deduction_amount_monthly' => 100,
            'remaining_amount' => 500,
            'status' => Advance::STATUS_ACTIVE,
        ]);

        $payroll = PayrollRecord::factory()->create([
            'user_id' => $employee->id,
            'month' => 6,
            'year' => 2026,
            'status' => PayrollRecord::STATUS_DRAFT,
            'created_by' => $manager->id,
            'net_pay' => 2808,
            'gross_pay' => 4000,
            'total_deductions' => 1192,
            'advance_deductions' => 100,
        ]);

        PayrollAdjustment::factory()->create([
            'payroll_record_id' => $payroll->id,
            'type' => PayrollAdjustment::TYPE_ADVANCE,
            'amount' => 100,
            'created_by' => $manager->id,
        ]);

        app(PayrollGenerator::class)->approve($payroll);
        $payroll->refresh();
        $advance->refresh();

        $this->assertSame(PayrollRecord::STATUS_APPROVED, $payroll->status);
        $this->assertSame(400.0, (float) $advance->remaining_amount);

        return [$employee, $payroll, $advance];
    }
}
