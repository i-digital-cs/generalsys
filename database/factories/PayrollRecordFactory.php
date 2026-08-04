<?php

namespace Database\Factories;

use App\Models\PayrollRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PayrollRecord> */
class PayrollRecordFactory extends Factory
{
    protected $model = PayrollRecord::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->employee(),
            'month' => 8,
            'year' => 2026,
            'currency' => 'EGP',
            'base_salary' => 6000,
            'status' => PayrollRecord::STATUS_DRAFT,
            'created_by' => User::factory()->manager(),
        ];
    }
}
