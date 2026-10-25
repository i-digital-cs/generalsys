<?php

namespace App\Models\GeneralHouse;

use App\Support\GeneralHouse\GhCatalogStatus;
use Database\Factories\GeneralHouse\GhProductOfferingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GhProductOffering extends Model
{
    /** @use HasFactory<GhProductOfferingFactory> */
    use HasFactory;

    protected $table = 'gh_product_offerings';

    protected $fillable = [
        'product_id',
        'treatment_id',
        'standard_price',
        'currency',
        'tax_class',
        'status',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'standard_price' => 'decimal:2',
            'sort_order' => 'integer',
            'product_id' => 'integer',
            'treatment_id' => 'integer',
        ];
    }

    protected static function newFactory(): GhProductOfferingFactory
    {
        return GhProductOfferingFactory::new();
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(GhProduct::class, 'product_id');
    }

    public function treatment(): BelongsTo
    {
        return $this->belongsTo(GhTreatment::class, 'treatment_id');
    }

    public function isActive(): bool
    {
        return $this->status === GhCatalogStatus::ACTIVE;
    }
}
