<?php

namespace App\Models\GeneralHouse;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GhDebitAdjustmentLine extends Model
{
    protected $table = 'gh_debit_adjustment_lines';

    /** @var list<string> */
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'line_net' => 'decimal:2',
            'line_vat' => 'decimal:2',
            'line_total' => 'decimal:2',
            'snapshot' => 'array',
        ];
    }

    public function debitAdjustment(): BelongsTo
    {
        return $this->belongsTo(GhDebitAdjustment::class, 'debit_adjustment_id');
    }

    public function invoiceLine(): BelongsTo
    {
        return $this->belongsTo(GhInvoiceLine::class, 'invoice_line_id');
    }

    public function offering(): BelongsTo
    {
        return $this->belongsTo(GhProductOffering::class, 'offering_id');
    }
}
