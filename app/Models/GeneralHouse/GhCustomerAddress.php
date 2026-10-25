<?php

namespace App\Models\GeneralHouse;

use Database\Factories\GeneralHouse\GhCustomerAddressFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GhCustomerAddress extends Model
{
    /** @use HasFactory<GhCustomerAddressFactory> */
    use HasFactory;

    protected $table = 'gh_customer_addresses';

    protected $fillable = [
        'customer_id',
        'zone_id',
        'label',
        'building',
        'street',
        'area',
        'directions',
        'latitude',
        'longitude',
        'is_default',
        'default_for_customer_id',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'customer_id' => 'integer',
            'zone_id' => 'integer',
            'default_for_customer_id' => 'integer',
        ];
    }

    protected static function newFactory(): GhCustomerAddressFactory
    {
        return GhCustomerAddressFactory::new();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(GhCustomer::class, 'customer_id');
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(GhZone::class, 'zone_id');
    }
}
