<?php

namespace App\Models\GeneralHouse;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GhInvoiceLine extends Model
{
    protected $table = 'gh_invoice_lines';

    protected $fillable = [
        'invoice_id', 'sort_order', 'received_line_id', 'offering_id',
        'category_id', 'product_id', 'treatment_id',
        'quantity', 'quantity_received', 'qty_differs_from_received',
        'unit_price', 'currency', 'price_source', 'contract_id',
        'tax_class', 'tax_rate',
        'item_discount_type', 'item_discount_value', 'item_discount_amount',
        'line_gross', 'line_after_item_discount', 'allocated_invoice_discount',
        'taxable_base', 'tax_amount', 'line_total',
        'product_name_ar', 'product_name_en', 'treatment_name_ar', 'treatment_name_en',
        'category_name_ar', 'category_name_en', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'quantity_received' => 'integer',
            'qty_differs_from_received' => 'boolean',
            'unit_price' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'item_discount_value' => 'decimal:2',
            'item_discount_amount' => 'decimal:2',
            'line_gross' => 'decimal:2',
            'line_after_item_discount' => 'decimal:2',
            'allocated_invoice_discount' => 'decimal:2',
            'taxable_base' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(GhInvoice::class, 'invoice_id');
    }

    public function localizedProductName(): string
    {
        return app()->getLocale() === 'ar'
            ? (string) $this->product_name_ar
            : (string) ($this->product_name_en ?: $this->product_name_ar);
    }

    public function localizedTreatmentName(): string
    {
        return app()->getLocale() === 'ar'
            ? (string) $this->treatment_name_ar
            : (string) ($this->treatment_name_en ?: $this->treatment_name_ar);
    }
}
