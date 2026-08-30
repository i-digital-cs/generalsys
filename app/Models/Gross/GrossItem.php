<?php

namespace App\Models\Gross;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class GrossItem extends Model
{
    public const STATUS_ACTIVE = 'ACTIVE';

    public const STATUS_INACTIVE = 'INACTIVE';

    protected $table = 'gross_items';

    protected $fillable = [
        'code',
        'name',
        'color',
        'size',
        'model',
        'color_id',
        'size_id',
        'model_id',
        'description',
        'category_id',
        'subcategory_id',
        'pieces_per_carton',
        'reorder_level',
        'default_sale_price',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'pieces_per_carton' => 'integer',
            'reorder_level' => 'integer',
            'default_sale_price' => 'decimal:2',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(GrossCategory::class, 'category_id');
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(GrossCategory::class, 'subcategory_id');
    }

    public function colorOption(): BelongsTo
    {
        return $this->belongsTo(GrossItemAttribute::class, 'color_id');
    }

    public function sizeOption(): BelongsTo
    {
        return $this->belongsTo(GrossItemAttribute::class, 'size_id');
    }

    public function modelOption(): BelongsTo
    {
        return $this->belongsTo(GrossItemAttribute::class, 'model_id');
    }

    public function balance(): HasOne
    {
        return $this->hasOne(GrossStockBalance::class, 'item_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(GrossStockMovement::class, 'item_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(GrossItemImage::class, 'item_id')->orderBy('sort_order')->orderBy('id');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isLowStock(): bool
    {
        $onHand = (int) ($this->balance?->qty_on_hand_pieces ?? 0);

        return $onHand <= (int) $this->reorder_level;
    }
}
