<?php

namespace Database\Factories;

use App\Models\PayrollAdjustment;
use App\Models\PayrollRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PayrollAdjustment> */
class PayrollAdjustmentFactory extends Factory
{
    protected $model = PayrollAdjustment::class;

    public function definition(): array
    {
        return [
            'payroll_record_id' => PayrollRecord::factory(),
            'type' => PayrollAdjustment::TYPE_BONUS,
            'amount' => 100,
            'note' => null,
            'created_by' => User::factory()->manager(),
        ];
    }
}
