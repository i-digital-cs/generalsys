<?php

namespace App\Models\Gross;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrossStockMovement extends Model
{
    public const TYPE_ADJUSTMENT_IN = 'ADJUSTMENT_IN';

    public const TYPE_ADJUSTMENT_OUT = 'ADJUSTMENT_OUT';

    public const TYPE_SALE_OUT = 'SALE_OUT';

    public const TYPE_SALE_RETURN = 'SALE_RETURN';

    /** Client refused at delivery — money adjusts; pieces still with courier (no stock move). */
    public const TYPE_SALE_RETURN_CLIENT = 'SALE_RETURN_CLIENT';

    public const TYPE_SALE_DELIVERED = 'SALE_DELIVERED';

    public const TYPE_REFUSE_RETURN = 'REFUSE_RETURN';

    public const TYPE_LOSS_CONFIRM = 'LOSS_CONFIRM';

    public const TYPE_CANCEL_RESTORE = 'CANCEL_RESTORE';

    public const TYPE_PURCHASE_IN = 'PURCHASE_IN';

    public const TYPE_PURCHASE_RETURN = 'PURCHASE_RETURN';

    public const UNIT_PIECE = 'PIECE';

    public const UNIT_CARTON = 'CARTON';

    public const UNITS = [self::UNIT_PIECE, self::UNIT_CARTON];

    protected $table = 'gross_stock_movements';

    protected $fillable = [
        'item_id',
        'type',
        'qty',
        'unit',
        'conversion_factor',
        'qty_pieces',
        'ref_type',
        'ref_id',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'conversion_factor' => 'integer',
            'qty_pieces' => 'integer',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(GrossItem::class, 'item_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function shipmentAllocation(): BelongsTo
    {
        return $this->belongsTo(GrossShipmentAllocation::class, 'ref_id');
    }
}
