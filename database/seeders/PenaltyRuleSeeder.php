<?php

namespace Database\Seeders;

use App\Models\PenaltyRule;
use Illuminate\Database\Seeder;

/**
 * Default per-currency penalty rates (mirrors legacy config/penalties.php).
 */
class PenaltyRuleSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            ['name' => 'Late arrival (EGP)', 'type' => PenaltyRule::TYPE_LATE_ARRIVAL, 'currency' => 'EGP', 'amount' => 1.00, 'unit' => PenaltyRule::UNIT_MINUTE],
            ['name' => 'Leave permission (EGP)', 'type' => PenaltyRule::TYPE_LEAVE_PERMISSION, 'currency' => 'EGP', 'amount' => 1.00, 'unit' => PenaltyRule::UNIT_MINUTE],
            ['name' => 'Late permission (EGP)', 'type' => PenaltyRule::TYPE_LATE_PERMISSION, 'currency' => 'EGP', 'amount' => 1.00, 'unit' => PenaltyRule::UNIT_MINUTE],
            ['name' => 'Excused absence (EGP)', 'type' => PenaltyRule::TYPE_EXCUSED_ABSENCE, 'currency' => 'EGP', 'amount' => 1.00, 'unit' => PenaltyRule::UNIT_DAY],
            ['name' => 'Unexcused absence (EGP)', 'type' => PenaltyRule::TYPE_UNEXCUSED_ABSENCE, 'currency' => 'EGP', 'amount' => 3.00, 'unit' => PenaltyRule::UNIT_DAY],
            ['name' => 'Late arrival (AED)', 'type' => PenaltyRule::TYPE_LATE_ARRIVAL, 'currency' => 'AED', 'amount' => 2.00, 'unit' => PenaltyRule::UNIT_MINUTE],
            ['name' => 'Leave permission (AED)', 'type' => PenaltyRule::TYPE_LEAVE_PERMISSION, 'currency' => 'AED', 'amount' => 2.00, 'unit' => PenaltyRule::UNIT_MINUTE],
            ['name' => 'Late permission (AED)', 'type' => PenaltyRule::TYPE_LATE_PERMISSION, 'currency' => 'AED', 'amount' => 2.00, 'unit' => PenaltyRule::UNIT_MINUTE],
            ['name' => 'Excused absence (AED)', 'type' => PenaltyRule::TYPE_EXCUSED_ABSENCE, 'currency' => 'AED', 'amount' => 1.00, 'unit' => PenaltyRule::UNIT_DAY],
            ['name' => 'Unexcused absence (AED)', 'type' => PenaltyRule::TYPE_UNEXCUSED_ABSENCE, 'currency' => 'AED', 'amount' => 3.00, 'unit' => PenaltyRule::UNIT_DAY],
        ];

        foreach ($defaults as $row) {
            PenaltyRule::query()->updateOrCreate(
                [
                    'type' => $row['type'],
                    'currency' => $row['currency'],
                ],
                [
                    'name' => $row['name'],
                    'amount_per_unit' => $row['amount'],
                    'unit' => $row['unit'],
                    'double_penalty_multiplier' => 2.00,
                    'is_active' => true,
                ],
            );
        }
    }
}
