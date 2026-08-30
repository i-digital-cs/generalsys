<?php

namespace App\Models\Gross;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GrossShippingCompany extends Model
{
    public const STATUS_ACTIVE = 'ACTIVE';

    public const STATUS_INACTIVE = 'INACTIVE';

    protected $table = 'gross_shipping_companies';

    protected $fillable = [
        'name',
        'phone',
        'address',
        'notes',
        'status',
    ];

    public function allocations(): HasMany
    {
        return $this->hasMany(GrossShipmentAllocation::class, 'shipping_company_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(GrossSalesInvoice::class, 'shipping_company_id');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
