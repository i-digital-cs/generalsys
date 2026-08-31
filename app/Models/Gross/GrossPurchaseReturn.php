<?php

namespace App\Models\Gross;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GrossPurchaseReturn extends Model
{
    protected $table = 'gross_purchase_returns';

    protected $fillable = [
        'return_number',
        'purchase_invoice_id',
        'notes',
        'created_by',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(GrossPurchaseInvoice::class, 'purchase_invoice_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(GrossPurchaseReturnLine::class, 'purchase_return_id');
    }
}
