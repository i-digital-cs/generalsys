<?php

namespace Database\Factories;

use App\Models\PenaltyRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PenaltyRule> */
class PenaltyRuleFactory extends Factory
{
    protected $model = PenaltyRule::class;

    public function definition(): array
    {
        return [
            'name' => 'Leave permission (EGP)',
            'type' => PenaltyRule::TYPE_LEAVE_PERMISSION,
            'currency' => PenaltyRule::CURRENCY_EGP,
            'amount_per_unit' => 1.00,
            'unit' => PenaltyRule::UNIT_MINUTE,
            'double_penalty_multiplier' => 2.00,
            'is_active' => true,
        ];
    }
}
