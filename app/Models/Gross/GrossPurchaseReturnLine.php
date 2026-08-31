<?php

namespace App\Models\Gross;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrossPurchaseReturnLine extends Model
{
    protected $table = 'gross_purchase_return_lines';

    protected $fillable = [
        'purchase_return_id',
        'purchase_invoice_line_id',
        'item_id',
        'qty',
        'unit',
        'conversion_factor',
        'qty_pieces',
        'buy_price',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'conversion_factor' => 'integer',
            'qty_pieces' => 'integer',
            'buy_price' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function purchaseReturn(): BelongsTo
    {
        return $this->belongsTo(GrossPurchaseReturn::class, 'purchase_return_id');
    }

    public function invoiceLine(): BelongsTo
    {
        return $this->belongsTo(GrossPurchaseInvoiceLine::class, 'purchase_invoice_line_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(GrossItem::class, 'item_id');
    }
}
