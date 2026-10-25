<?php

namespace App\Models\GeneralHouse;

use App\Support\GeneralHouse\GhCatalogStatus;
use Database\Factories\GeneralHouse\GhB2bContractOfferingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GhB2bContractOffering extends Model
{
    /** @use HasFactory<GhB2bContractOfferingFactory> */
    use HasFactory;

    protected $table = 'gh_b2b_contract_offerings';

    protected $fillable = [
        'contract_id',
        'offering_id',
        'contract_price',
        'currency',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'contract_price' => 'decimal:2',
            'contract_id' => 'integer',
            'offering_id' => 'integer',
        ];
    }

    protected static function newFactory(): GhB2bContractOfferingFactory
    {
        return GhB2bContractOfferingFactory::new();
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(GhB2bContract::class, 'contract_id');
    }

    public function offering(): BelongsTo
    {
        return $this->belongsTo(GhProductOffering::class, 'offering_id');
    }

    public function isActive(): bool
    {
        return $this->status === GhCatalogStatus::ACTIVE;
    }
}
