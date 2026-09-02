<?php

namespace App\Models\Gross;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class GrossSalesInvoiceLine extends Model
{
    public const UNIT_PIECE = 'PIECE';

    public const UNIT_CARTON = 'CARTON';

    protected $table = 'gross_sales_invoice_lines';

    protected $fillable = [
        'sales_invoice_id',
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
        'unit_price',
        'discount_amount',
        'discount_percent',
        'line_total',
        'returned_amount',
        'lost_amount',
        'net_line_total',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'conversion_factor_snapshot' => 'integer',
            'qty_pieces' => 'integer',
            'unit_price' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'line_total' => 'decimal:2',
            'returned_amount' => 'decimal:2',
            'lost_amount' => 'decimal:2',
            'net_line_total' => 'decimal:2',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(GrossSalesInvoice::class, 'sales_invoice_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(GrossItem::class, 'item_id');
    }

    public function allocation(): HasOne
    {
        return $this->hasOne(GrossShipmentAllocation::class, 'sales_invoice_line_id');
    }
}
