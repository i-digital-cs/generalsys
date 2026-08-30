<?php

namespace App\Models\Gross;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrossPurchaseInvoiceLine extends Model
{
    public const UNIT_PIECE = 'PIECE';

    public const UNIT_CARTON = 'CARTON';

    protected $table = 'gross_purchase_invoice_lines';

    protected $fillable = [
        'purchase_invoice_id',
        'item_id',
        'item_code_snapshot',
        'item_name_snapshot',
        'color_snapshot',
        'size_snapshot',
        'model_snapshot',
        'qty',
        'unit',
        'conversion_factor_snapshot',
        'qty_pieces',
        'buy_price',
        'sale_price',
        'discount_amount',
        'discount_percent',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'conversion_factor_snapshot' => 'integer',
            'qty_pieces' => 'integer',
            'buy_price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(GrossPurchaseInvoice::class, 'purchase_invoice_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(GrossItem::class, 'item_id');
    }
}
