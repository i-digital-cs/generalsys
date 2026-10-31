<?php

namespace App\Models\GeneralHouse;

use App\Casts\GhUtcDateTime;
use App\Models\User;
use App\Support\GeneralHouse\GhInvoiceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class GhInvoice extends Model
{
    protected $table = 'gh_invoices';

    protected $fillable = [
        'order_id', 'number', 'status', 'payment_status', 'currency',
        'customer_id', 'customer_type', 'customer_name_ar', 'customer_name_en', 'customer_phone',
        'billing_name', 'billing_address', 'tax_registration_number',
        'order_reference', 'zone_id', 'zone_name_ar', 'zone_name_en',
        'contract_id', 'contract_reference',
        'lines_gross', 'item_discount_total',
        'invoice_discount_type', 'invoice_discount_value', 'invoice_discount_amount',
        'lines_after_discounts',
        'min_order_original_amount', 'min_order_effective_amount', 'min_order_policy',
        'min_order_override_type', 'min_order_adjustment_amount', 'min_order_tax_class',
        'taxable_base', 'vat_amount', 'total_payable', 'standard_vat_rate',
        'draft_version', 'issued_at', 'issued_by', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'lines_gross' => 'decimal:2',
            'item_discount_total' => 'decimal:2',
            'invoice_discount_value' => 'decimal:2',
            'invoice_discount_amount' => 'decimal:2',
            'lines_after_discounts' => 'decimal:2',
            'min_order_original_amount' => 'decimal:2',
            'min_order_effective_amount' => 'decimal:2',
            'min_order_adjustment_amount' => 'decimal:2',
            'taxable_base' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'total_payable' => 'decimal:2',
            'standard_vat_rate' => 'decimal:2',
            'draft_version' => 'integer',
            'issued_at' => GhUtcDateTime::class,
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(GhOrder::class, 'order_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(GhInvoiceLine::class, 'invoice_id')->orderBy('sort_order')->orderBy('id');
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(GhInvoiceAdjustment::class, 'invoice_id');
    }

    public function minOrderOverride(): HasOne
    {
        return $this->hasOne(GhMinOrderOverride::class, 'invoice_id')->latestOfMany('id');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function isDraft(): bool
    {
        return $this->status === GhInvoiceStatus::DRAFT;
    }

    public function isIssued(): bool
    {
        return $this->status === GhInvoiceStatus::ISSUED;
    }
}
