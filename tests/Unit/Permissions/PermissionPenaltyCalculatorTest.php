<?php

namespace Tests\Unit\Permissions;

use App\Models\PenaltyRule;
use App\Models\Permission;
use App\Models\User;
use App\Services\Permissions\PermissionPenaltyCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionPenaltyCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_egp_leave_permission_is_minutes_times_rate(): void
    {
        $user = User::factory()->employee()->create(['currency' => 'EGP']);
        $permission = Permission::factory()->make([
            'user_id' => $user->id,
            'type' => Permission::TYPE_LEAVE,
            'from_time' => '10:00',
            'to_time' => '10:30',
        ]);
        $permission->setRelation('user', $user);

        $calc = app(PermissionPenaltyCalculator::class);

        $this->assertSame(30, $calc->minutes($permission));
        $this->assertSame(30.0, $calc->amountForRejection($permission));
    }

    public function test_aed_uses_currency_rate(): void
    {
        $user = User::factory()->employee()->create(['currency' => 'AED']);
        $permission = Permission::factory()->make([
            'user_id' => $user->id,
            'type' => Permission::TYPE_LATE,
            'from_time' => '10:00',
            'to_time' => '10:15',
        ]);
        $permission->setRelation('user', $user);

        $this->assertSame(30.0, app(PermissionPenaltyCalculator::class)->amountForRejection($permission));
    }

    public function test_uses_active_penalty_rule_over_config(): void
    {
        $employee = User::factory()->employee()->create(['currency' => 'EGP']);

        PenaltyRule::factory()->create([
            'type' => PenaltyRule::TYPE_LEAVE_PERMISSION,
            'currency' => 'EGP',
            'amount_per_unit' => 3.50,
            'double_penalty_multiplier' => 2.5,
            'is_active' => true,
        ]);

        $permission = Permission::factory()->create([
            'user_id' => $employee->id,
            'type' => Permission::TYPE_LEAVE,
            'from_time' => '14:00',
            'to_time' => '16:00',
        ]);

        $calc = app(PermissionPenaltyCalculator::class);

        $this->assertSame(3.5, $calc->ratePerMinute($employee, Permission::TYPE_LEAVE));
        $this->assertSame(2.5, $calc->doubleMultiplier($employee, Permission::TYPE_LEAVE));
        $this->assertSame(420.0, $calc->amountForRejection($permission));
    }
}
