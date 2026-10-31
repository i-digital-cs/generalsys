<?php

namespace App\Models\GeneralHouse;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GhInvoiceAdjustment extends Model
{
    protected $table = 'gh_invoice_adjustments';

    protected $fillable = [
        'invoice_id', 'adjustment_type', 'amount',
        'tax_class', 'tax_rate', 'tax_amount',
        'label_ar', 'label_en', 'payload',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'payload' => 'array',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(GhInvoice::class, 'invoice_id');
    }
}
