<?php

namespace Tests\Feature\Admin;

use App\Models\PayrollAdjustment;
use App\Models\PayrollRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Gate 4.7 / 4.9 — TEAM_LEADER blocked from every payroll route + no payroll menu.
 */
class PayrollPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_leader_gets_403_on_every_payroll_route(): void
    {
        $manager = User::factory()->manager()->create();
        $leader = User::factory()->teamLeader()->create();
        $employee = User::factory()->employee()->create(['team_leader_id' => $leader->id]);

        $payroll = PayrollRecord::factory()->create([
            'user_id' => $employee->id,
            'created_by' => $manager->id,
            'status' => PayrollRecord::STATUS_DRAFT,
        ]);
        $adjustment = PayrollAdjustment::factory()->create([
            'payroll_record_id' => $payroll->id,
            'created_by' => $manager->id,
        ]);

        $this->actingAs($leader);

        $this->get(route('admin.payroll.index'))->assertForbidden();
        $this->get(route('admin.payroll.show', $payroll))->assertForbidden();
        $this->post(route('admin.payroll.generate'), [
            'month' => 6,
            'year' => 2026,
        ])->assertForbidden();
        $this->put(route('admin.payroll.update', $payroll), [
            'full_time_days' => 10,
            'online_half_days' => 0,
            'online_full_days' => 0,
            'rest_days' => 0,
            'extra_shift_hours' => 0,
            'overtime_hours' => 0,
            'friday_work_days' => 0,
            'day_off_work_days' => 0,
            'official_holiday_work_days' => 0,
            'hours_deduction' => 0,
            'late_minutes_total' => 0,
        ])->assertForbidden();
        $this->post(route('admin.payroll.adjustments.store', $payroll), [
            'type' => PayrollAdjustment::TYPE_BONUS,
            'amount' => 10,
        ])->assertForbidden();
        $this->delete(route('admin.payroll.adjustments.destroy', [$payroll, $adjustment]))
            ->assertForbidden();
        $this->put(route('admin.payroll.approve', $payroll))->assertForbidden();
    }

    public function test_team_leader_sidebar_has_no_payroll_menu(): void
    {
        $leader = User::factory()->teamLeader()->create();

        $this->actingAs($leader)
            ->get(route('team-leader.dashboard'))
            ->assertOk()
            ->assertDontSee(__('nav.payroll'), false)
            ->assertDontSee(route('admin.payroll.index'), false);
    }

    public function test_manager_sidebar_includes_payroll(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(__('nav.payroll'), false);
    }
}
