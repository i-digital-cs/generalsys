<?php

namespace App\Models\GeneralHouse;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GhCreditNoteLine extends Model
{
    protected $table = 'gh_credit_note_lines';

    /** @var list<string> */
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'quantity_credited' => 'integer',
            'unit_price' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'line_net' => 'decimal:2',
            'line_vat' => 'decimal:2',
            'line_total' => 'decimal:2',
            'snapshot' => 'array',
        ];
    }

    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(GhCreditNote::class, 'credit_note_id');
    }

    public function invoiceLine(): BelongsTo
    {
        return $this->belongsTo(GhInvoiceLine::class, 'invoice_line_id');
    }
}
