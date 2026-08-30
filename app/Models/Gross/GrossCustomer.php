<?php

namespace App\Models\Gross;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GrossCustomer extends Model
{
    protected $table = 'gross_customers';

    protected $fillable = [
        'name',
        'phone',
        'address',
    ];

    public function invoices(): HasMany
    {
        return $this->hasMany(GrossSalesInvoice::class, 'customer_id');
    }
}
