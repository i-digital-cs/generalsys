<?php

namespace Tests\Feature\Admin;

use App\Models\PayrollAdjustment;
use App\Models\PayrollRecord;
use App\Models\User;
use App\Services\Payroll\PayrollDraftService;
use App\Services\Payroll\PayrollGenerator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 3 — settled payroll freeze, generate skip, approve idempotency, privacy edges.
 */
class PayrollImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_cannot_be_updated_or_refreshed_via_http(): void
    {
        [$manager, $payroll] = $this->settledPayslip(PayrollRecord::STATUS_APPROVED, net: 3333.0);

        $this->actingAs($manager)
            ->put(route('admin.payroll.update', $payroll), $this->countPayload())
            ->assertForbidden();

        $this->actingAs($manager)
            ->post(route('admin.payroll.refresh-attendance', $payroll))
            ->assertForbidden();

        $this->actingAs($manager)
            ->post(route('admin.payroll.adjustments.store', $payroll), [
                'type' => PayrollAdjustment::TYPE_BONUS,
                'amount' => 50,
            ])
            ->assertForbidden();

        $this->assertSame(3333.0, (float) $payroll->fresh()->net_pay);
        $this->assertSame(PayrollRecord::STATUS_APPROVED, $payroll->fresh()->status);
    }

    public function test_paid_cannot_be_updated_or_refreshed_via_http(): void
    {
        [$manager, $payroll] = $this->settledPayslip(PayrollRecord::STATUS_PAID, net: 2222.0);

        $this->actingAs($manager)
            ->put(route('admin.payroll.update', $payroll), $this->countPayload())
            ->assertForbidden();

        $this->actingAs($manager)
            ->post(route('admin.payroll.refresh-attendance', $payroll))
            ->assertForbidden();

        $this->assertSame(2222.0, (float) $payroll->fresh()->net_pay);
        $this->assertSame(PayrollRecord::STATUS_PAID, $payroll->fresh()->status);
    }

    public function test_service_layer_rejects_refresh_on_approved(): void
    {
        [, $payroll] = $this->settledPayslip(PayrollRecord::STATUS_APPROVED, net: 1500.0);

        $this->expectException(AuthorizationException::class);
        app(PayrollDraftService::class)->refreshFromAttendance($payroll);
    }

    public function test_generate_does_not_overwrite_approved_totals(): void
    {
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee()->create([
            'base_salary' => 5000,
            'currency' => 'EGP',
        ]);
        $approved = PayrollRecord::factory()->create([
            'user_id' => $employee->id,
            'created_by' => $manager->id,
            'month' => 9,
            'year' => 2026,
            'status' => PayrollRecord::STATUS_APPROVED,
            'net_pay' => 4444.44,
            'late_minutes_total' => 99,
        ]);

        $returned = app(PayrollGenerator::class)->generateForUser($employee, 9, 2026, $manager->id);

        $this->assertTrue($returned->is($approved->fresh()));
        $this->assertSame(PayrollRecord::STATUS_APPROVED, $returned->status);
        $this->assertSame('4444.44', (string) $returned->net_pay);
        $this->assertSame(99, (int) $returned->late_minutes_total);
    }

    public function test_approve_is_idempotent_and_does_not_revert_status(): void
    {
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee()->create();
        $draft = PayrollRecord::factory()->create([
            'user_id' => $employee->id,
            'created_by' => $manager->id,
            'month' => 8,
            'year' => 2026,
            'status' => PayrollRecord::STATUS_DRAFT,
            'net_pay' => 2808,
        ]);

        $generator = app(PayrollGenerator::class);
        $first = $generator->approve($draft);
        $this->assertSame(PayrollRecord::STATUS_APPROVED, $first->status);

        $second = $generator->approve($first);
        $this->assertSame(PayrollRecord::STATUS_APPROVED, $second->status);
        $this->assertSame('2808.00', (string) $second->fresh()->net_pay);
    }

    public function test_accountant_can_edit_draft_but_cannot_approve(): void
    {
        $manager = User::factory()->manager()->create();
        $accountant = User::factory()->accountant()->create();
        $employee = User::factory()->employee()->create();
        $draft = PayrollRecord::factory()->create([
            'user_id' => $employee->id,
            'created_by' => $manager->id,
            'month' => 7,
            'year' => 2026,
            'status' => PayrollRecord::STATUS_DRAFT,
            'full_time_days' => 10,
        ]);

        $this->actingAs($accountant)
            ->put(route('admin.payroll.update', $draft), $this->countPayload([
                'full_time_days' => 12,
            ]))
            ->assertRedirect(route('admin.payroll.show', $draft));

        $this->assertSame(12, (int) $draft->fresh()->full_time_days);

        $this->actingAs($accountant)
            ->put(route('admin.payroll.approve', $draft))
            ->assertForbidden();

        $this->assertSame(PayrollRecord::STATUS_DRAFT, $draft->fresh()->status);
    }

    public function test_team_leader_cannot_export_payroll_pdf(): void
    {
        $manager = User::factory()->manager()->create();
        $leader = User::factory()->teamLeader()->create();
        $employee = User::factory()->employee()->create(['team_leader_id' => $leader->id]);
        $payroll = PayrollRecord::factory()->create([
            'user_id' => $employee->id,
            'created_by' => $manager->id,
            'status' => PayrollRecord::STATUS_APPROVED,
        ]);

        $this->actingAs($leader)
            ->get(route('admin.payroll.export.pdf', $payroll))
            ->assertForbidden();

        $this->actingAs($leader)
            ->get(route('admin.payroll.export.review.pdf', $payroll))
            ->assertForbidden();
    }

    /**
     * @return array{0: User, 1: PayrollRecord}
     */
    private function settledPayslip(string $status, float $net): array
    {
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee()->create();
        $payroll = PayrollRecord::factory()->create([
            'user_id' => $employee->id,
            'created_by' => $manager->id,
            'month' => 9,
            'year' => 2026,
            'status' => $status,
            'net_pay' => $net,
            'late_minutes_total' => 42,
        ]);

        return [$manager, $payroll];
    }

    /**
     * @param  array<string, int|float>  $overrides
     * @return array<string, int|float>
     */
    private function countPayload(array $overrides = []): array
    {
        return array_merge([
            'full_time_days' => 18,
            'online_half_days' => 0,
            'online_full_days' => 0,
            'rest_days' => 4,
            'overtime_hours' => 0,
            'overtime_multiplier' => 1.5,
            'day_off_work_days' => 0,
            'official_holiday_work_days' => 0,
            'hours_deduction' => 0,
            'excused_absence_days' => 0,
            'unexcused_absence_days' => 0,
            'late_minutes_total' => 0,
            'extra_days' => 0,
            'extra_pay' => 0,
        ], $overrides);
    }
}
