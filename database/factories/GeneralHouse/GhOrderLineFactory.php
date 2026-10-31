<?php

namespace Database\Factories\GeneralHouse;

use App\Models\GeneralHouse\GhOrder;
use App\Models\GeneralHouse\GhOrderLine;
use App\Models\GeneralHouse\GhProductOffering;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GhOrderLine> */
class GhOrderLineFactory extends Factory
{
    protected $model = GhOrderLine::class;

    public function definition(): array
    {
        return [
            'order_id' => GhOrder::factory(),
            'offering_id' => GhProductOffering::factory(),
            'category_id' => null,
            'product_id' => null,
            'treatment_id' => null,
            'quantity' => 1,
            'unit_price_estimate' => '10.00',
            'currency' => 'AED',
            'price_source' => 'STANDARD',
            'contract_id' => null,
            'product_name_ar' => 'منتج',
            'product_name_en' => 'Product',
            'treatment_name_ar' => 'كي',
            'treatment_name_en' => 'Iron',
            'category_name_ar' => null,
            'category_name_en' => null,
            'sort_order' => 0,
        ];
    }
}
