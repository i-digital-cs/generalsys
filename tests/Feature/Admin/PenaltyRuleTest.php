<?php

namespace Tests\Feature\Admin;

use App\Models\PenaltyRule;
use App\Models\User;
use Database\Seeders\PenaltyRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T-4.2 — Admin penalty rules CRUD; TL 403.
 */
class PenaltyRuleTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = User::factory()->manager()->create(['language_preference' => 'en']);
        $this->seed(PenaltyRuleSeeder::class);
    }

    public function test_manager_sees_penalty_rules_grid(): void
    {
        app()->setLocale('en');

        $this->actingAs($this->manager)
            ->get(route('admin.config.penalties'))
            ->assertOk()
            ->assertSee(__('penalties.title'), false)
            ->assertSee(__('penalties.types.LEAVE_PERMISSION'), false)
            ->assertSee(__('penalties.create'), false);
    }

    public function test_manager_can_update_rule_amount(): void
    {
        $rule = PenaltyRule::query()
            ->where('type', PenaltyRule::TYPE_LEAVE_PERMISSION)
            ->where('currency', 'EGP')
            ->firstOrFail();

        $this->actingAs($this->manager)
            ->put(route('admin.config.penalties.update', $rule), [
                'name' => 'Leave permission (EGP)',
                'amount_per_unit' => 4.25,
                'unit' => PenaltyRule::UNIT_MINUTE,
                'double_penalty_multiplier' => 2.00,
                'is_active' => 1,
            ])
            ->assertRedirect(route('admin.config.penalties'))
            ->assertSessionHas('success');

        $this->assertSame('4.25', (string) $rule->fresh()->amount_per_unit);
    }

    public function test_manager_can_create_rule_for_missing_pair(): void
    {
        PenaltyRule::query()
            ->where('type', PenaltyRule::TYPE_LATE_ARRIVAL)
            ->where('currency', 'AED')
            ->delete();

        $this->actingAs($this->manager)
            ->post(route('admin.config.penalties.store'), [
                'name' => 'Late arrival (AED)',
                'type' => PenaltyRule::TYPE_LATE_ARRIVAL,
                'currency' => 'AED',
                'amount_per_unit' => 2.50,
                'unit' => PenaltyRule::UNIT_MINUTE,
                'double_penalty_multiplier' => 2.00,
                'is_active' => 1,
            ])
            ->assertRedirect(route('admin.config.penalties'));

        $this->assertDatabaseHas('penalty_rules', [
            'type' => PenaltyRule::TYPE_LATE_ARRIVAL,
            'currency' => 'AED',
            'amount_per_unit' => 2.50,
        ]);
    }

    public function test_validation_rejects_invalid_amount(): void
    {
        $rule = PenaltyRule::query()->firstOrFail();

        $this->actingAs($this->manager)
            ->put(route('admin.config.penalties.update', $rule), [
                'name' => $rule->name,
                'amount_per_unit' => -1,
                'unit' => $rule->unit,
                'double_penalty_multiplier' => 2,
                'is_active' => 1,
            ])
            ->assertSessionHasErrors('amount_per_unit');
    }

    public function test_team_leader_gets_403(): void
    {
        $leader = User::factory()->teamLeader()->create();

        $this->actingAs($leader)
            ->get(route('admin.config.penalties'))
            ->assertForbidden();
    }

    public function test_employee_gets_403(): void
    {
        $employee = User::factory()->employee()->create();

        $this->actingAs($employee)
            ->get(route('admin.config.penalties'))
            ->assertForbidden();
    }
}
