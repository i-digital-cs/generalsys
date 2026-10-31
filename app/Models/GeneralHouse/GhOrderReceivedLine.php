<?php

namespace App\Models\GeneralHouse;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GhOrderReceivedLine extends Model
{
    protected $table = 'gh_order_received_lines';

    protected $fillable = [
        'order_id',
        'order_line_id',
        'offering_id',
        'quantity',
        'discrepancy_type',
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
        'notes',
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

    public function order(): BelongsTo
    {
        return $this->belongsTo(GhOrder::class, 'order_id');
    }

    public function orderLine(): BelongsTo
    {
        return $this->belongsTo(GhOrderLine::class, 'order_line_id');
    }

    public function offering(): BelongsTo
    {
        return $this->belongsTo(GhProductOffering::class, 'offering_id');
    }
}
