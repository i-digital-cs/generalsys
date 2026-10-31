<?php

namespace App\Models\GeneralHouse;

use Database\Factories\GeneralHouse\GhOrderLineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GhOrderLine extends Model
{
    /** @use HasFactory<GhOrderLineFactory> */
    use HasFactory;

    protected $table = 'gh_order_lines';

    protected $fillable = [
        'order_id',
        'offering_id',
        'category_id',
        'product_id',
        'treatment_id',
        'quantity',
        'unit_price_estimate',
        'currency',
        'price_source',
        'contract_id',
        'product_name_ar',
        'product_name_en',
        'treatment_name_ar',
        'treatment_name_en',
        'category_name_ar',
        'category_name_en',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price_estimate' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    protected static function newFactory(): GhOrderLineFactory
    {
        return GhOrderLineFactory::new();
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(GhOrder::class, 'order_id');
    }

    public function offering(): BelongsTo
    {
        return $this->belongsTo(GhProductOffering::class, 'offering_id');
    }

    public function lineEstimateTotal(): string
    {
        return number_format((float) $this->unit_price_estimate * $this->quantity, 2, '.', '');
    }
}
