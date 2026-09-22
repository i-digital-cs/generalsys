<?php

namespace Database\Factories;

use App\Models\Gross\GrossReceiptPurpose;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GrossReceiptPurpose> */
class GrossReceiptPurposeFactory extends Factory
{
    protected $model = GrossReceiptPurpose::class;

    public function definition(): array
    {
        $en = fake()->unique()->words(2, true);

        return [
            'name_ar' => 'غرض '.$en,
            'name_en' => ucwords($en),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
