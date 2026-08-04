<?php

namespace Tests\Feature;

use App\Models\Advance;
use App\Models\PayrollAdjustment;
use App\Models\PayrollRecord;
use App\Models\PenaltyRule;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * T-4.1 — Phase 4 payroll / penalty schema is present and writable.
 */
class PayrollSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_phase4_tables_exist(): void
    {
        foreach (['penalty_rules', 'payroll_records', 'payroll_adjustments', 'advances'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table: {$table}");
        }
    }

    public function test_penalty_rule_unique_per_type_currency(): void
    {
        PenaltyRule::factory()->create([
            'type' => PenaltyRule::TYPE_LEAVE_PERMISSION,
            'currency' => 'EGP',
        ]);

        $this->expectException(QueryException::class);

        PenaltyRule::factory()->create([
            'type' => PenaltyRule::TYPE_LEAVE_PERMISSION,
            'currency' => 'EGP',
        ]);
    }

    public function test_payroll_record_unique_per_user_month_year(): void
    {
        $employee = User::factory()->employee()->create();
        $admin = User::factory()->manager()->create();

        PayrollRecord::factory()->create([
            'user_id' => $employee->id,
            'month' => 8,
            'year' => 2026,
            'created_by' => $admin->id,
        ]);

        $this->expectException(QueryException::class);

        PayrollRecord::factory()->create([
            'user_id' => $employee->id,
            'month' => 8,
            'year' => 2026,
            'created_by' => $admin->id,
        ]);
    }

    public function test_factories_persist_related_phase4_rows(): void
    {
        $admin = User::factory()->manager()->create();
        $employee = User::factory()->employee()->create();

        $rule = PenaltyRule::factory()->create([
            'type' => PenaltyRule::TYPE_LATE_PERMISSION,
            'currency' => 'AED',
            'amount_per_unit' => 2.50,
        ]);

        $payroll = PayrollRecord::factory()->create([
            'user_id' => $employee->id,
            'created_by' => $admin->id,
            'net_pay' => 2808.00,
        ]);

        $adjustment = PayrollAdjustment::factory()->create([
            'payroll_record_id' => $payroll->id,
            'created_by' => $admin->id,
            'type' => PayrollAdjustment::TYPE_SALES_COMMISSION,
            'amount' => 150,
        ]);

        $advance = Advance::factory()->create([
            'user_id' => $employee->id,
            'amount' => 300,
            'remaining_amount' => 300,
        ]);

        $this->assertTrue(PenaltyRule::query()->whereKey($rule)->exists());
        $this->assertTrue($employee->payrollRecords()->whereKey($payroll)->exists());
        $this->assertTrue($payroll->adjustments()->whereKey($adjustment)->exists());
        $this->assertTrue($employee->advances()->whereKey($advance)->exists());
        $this->assertSame('2808.00', (string) $payroll->fresh()->net_pay);
    }
}
