<?php

namespace App\Models\Gross;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class GrossSalesInvoice extends Model
{
    public const STATUS_DRAFT = 'DRAFT';

    public const STATUS_CONFIRMED = 'CONFIRMED';

    public const STATUS_DELIVERED = 'DELIVERED';

    public const STATUS_REFUSED = 'REFUSED';

    public const STATUS_CANCELLED = 'CANCELLED';

    public const SHIPPING_FEE_NONE = 'NONE';

    public const SHIPPING_FEE_PENDING = 'PENDING';

    public const SHIPPING_FEE_LOST = 'LOST';

    public const SHIPPING_FEE_SETTLED = 'SETTLED';

    public const SHIPPING_FEE_COLLECTED = 'COLLECTED';

    protected $table = 'gross_sales_invoices';

    protected $fillable = [
        'invoice_number',
        'customer_id',
        'customer_name',
        'customer_phone',
        'customer_address',
        'sales_user_id',
        'shipping_company_id',
        'shipping_company_name',
        'currency',
        'shipping_fee',
        'shipping_fee_status',
        'discount_amount',
        'discount_percent',
        'subtotal',
        'total',
        'status',
        'notes',
        'refused_at',
        'refused_by',
        'refuse_reason',
        'confirmed_at',
        'cancelled_at',
        'shipping_fee_collected_at',
        'shipping_fee_collected_by',
        'delivered_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'shipping_fee' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'total' => 'decimal:2',
            'refused_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'shipping_fee_collected_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(GrossCustomer::class, 'customer_id');
    }

    public function shippingCompany(): BelongsTo
    {
        return $this->belongsTo(GrossShippingCompany::class, 'shipping_company_id');
    }

    public function salesUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function refusedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'refused_by');
    }

    public function shippingFeeCollectedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shipping_fee_collected_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(GrossSalesInvoiceLine::class, 'sales_invoice_id');
    }

    public function allocations(): HasManyThrough
    {
        return $this->hasManyThrough(
            GrossShipmentAllocation::class,
            GrossSalesInvoiceLine::class,
            'sales_invoice_id',
            'sales_invoice_line_id',
        );
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    public function isDelivered(): bool
    {
        return $this->status === self::STATUS_DELIVERED;
    }

    public function shippingFeeIsPending(): bool
    {
        return $this->shipping_fee_status === self::SHIPPING_FEE_PENDING;
    }

    public function canMarkDelivered(): bool
    {
        if (! $this->isConfirmed()) {
            return false;
        }

        return in_array($this->shipping_fee_status, [
            self::SHIPPING_FEE_NONE,
            self::SHIPPING_FEE_COLLECTED,
            self::SHIPPING_FEE_SETTLED,
        ], true);
    }
}
