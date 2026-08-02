<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\VacationBalance;
use App\Models\VacationCreditLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<VacationCreditLog> */
class VacationCreditLogFactory extends Factory
{
    protected $model = VacationCreditLog::class;

    public function definition(): array
    {
        return [
            'vacation_balance_id' => VacationBalance::factory(),
            'added_by' => User::factory()->teamLeader(),
            'amount_days' => 2,
            'reason' => 'test credit',
            'created_at' => now(),
        ];
    }
}
