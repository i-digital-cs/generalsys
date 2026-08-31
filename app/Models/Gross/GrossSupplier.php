<?php

namespace App\Models\Gross;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GrossSupplier extends Model
{
    protected $table = 'gross_suppliers';

    protected $fillable = [
        'name',
        'phone',
        'address',
        'notes',
    ];

    public function purchaseInvoices(): HasMany
    {
        return $this->hasMany(GrossPurchaseInvoice::class, 'supplier_id');
    }
}
