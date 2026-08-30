<?php

namespace App\Models\Gross;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrossStockBalance extends Model
{
    protected $table = 'gross_stock_balances';

    protected $fillable = [
        'item_id',
        'qty_on_hand_pieces',
        'qty_with_courier_pieces',
        'qty_lost_pieces',
    ];

    protected function casts(): array
    {
        return [
            'qty_on_hand_pieces' => 'integer',
            'qty_with_courier_pieces' => 'integer',
            'qty_lost_pieces' => 'integer',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(GrossItem::class, 'item_id');
    }
}
