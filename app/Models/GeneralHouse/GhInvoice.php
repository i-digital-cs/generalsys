<?php

namespace App\Models\GeneralHouse;

use App\Casts\GhUtcDateTime;
use App\Models\User;
use App\Support\GeneralHouse\GhInvoiceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Invoice writes go through invoice/payment services via forceFill/forceCreate.
 * Empty $fillable blocks HTTP/mass-assignment of status, totals, and payment_status.
 */
class GhInvoice extends Model
{
    protected $table = 'gh_invoices';

    /** @var list<string> */
    protected $fillable = [];

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

    public function payments(): HasMany
    {
        return $this->hasMany(GhPayment::class, 'invoice_id')->orderByDesc('id');
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(GhCreditNote::class, 'invoice_id')->orderByDesc('id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(GhRefund::class, 'invoice_id')->orderByDesc('id');
    }

    public function financialCorrections(): HasMany
    {
        return $this->hasMany(GhFinancialCorrection::class, 'invoice_id')->orderByDesc('id');
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
