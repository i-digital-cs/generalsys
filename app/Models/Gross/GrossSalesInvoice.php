<?php

namespace App\Models\Gross;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    public const COD_NONE = 'NONE';

    public const COD_PENDING = 'PENDING';

    public const COD_COLLECTED = 'COLLECTED';

    public const COD_LOST = 'LOST';

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
        'returned_merchandise',
        'net_subtotal',
        'net_discount',
        'net_total',
        'status',
        'notes',
        'refused_at',
        'refused_by',
        'refuse_reason',
        'confirmed_at',
        'cancelled_at',
        'shipping_fee_collected_at',
        'shipping_fee_collected_by',
        'cod_collection_status',
        'cod_collected_amount',
        'cod_collected_at',
        'cod_collected_by',
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
            'returned_merchandise' => 'decimal:2',
            'net_subtotal' => 'decimal:2',
            'net_discount' => 'decimal:2',
            'net_total' => 'decimal:2',
            'cod_collected_amount' => 'decimal:2',
            'refused_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'shipping_fee_collected_at' => 'datetime',
            'cod_collected_at' => 'datetime',
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

    public function codCollectedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cod_collected_by');
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

    public function courierCodTreasuryVoucher(): HasOne
    {
        return $this->hasOne(GrossTreasuryVoucher::class, 'sales_invoice_id')
            ->where('purpose', GrossTreasuryVoucher::PURPOSE_COURIER_COD);
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

    public function isRefused(): bool
    {
        return $this->status === self::STATUS_REFUSED;
    }

    public function refusedPiecesPendingHandover(): int
    {
        if (! $this->isRefused()) {
            return 0;
        }

        $this->loadMissing('lines.allocation');

        return (int) $this->lines->sum(
            fn ($line) => (int) ($line->allocation?->returnedPendingHandover() ?? 0),
        );
    }

    public function isRefusedHandoverComplete(): bool
    {
        return $this->isRefused() && $this->refusedPiecesPendingHandover() < 1;
    }

    public function deliveryFeeOwed(): float
    {
        if (! $this->isRefused() || (float) $this->shipping_fee <= 0) {
            return 0.0;
        }

        return (float) $this->shipping_fee;
    }

    public function shippingFeeIsPending(): bool
    {
        return $this->shipping_fee_status === self::SHIPPING_FEE_PENDING;
    }

    public function codIsPending(): bool
    {
        return $this->cod_collection_status === self::COD_PENDING;
    }

    public function collectibleAmount(): float
    {
        return $this->courierMerchandiseAmount();
    }

    /** Merchandise owed by courier (shipping fee stays with courier). */
    public function courierMerchandiseAmount(): float
    {
        return max(0, round((float) $this->net_subtotal - (float) $this->net_discount, 2));
    }

    /** Merchandise on invoice at confirm (excludes shipping fee). */
    public function originalMerchandiseAmount(): float
    {
        $subtotal = (float) $this->subtotal;
        $percent = (float) $this->discount_percent;
        if ($percent > 0) {
            return max(0, round($subtotal * (1 - $percent / 100), 2));
        }

        return max(0, round($subtotal - (float) $this->discount_amount, 2));
    }

    public function canMarkDelivered(): bool
    {
        if (! $this->isConfirmed()) {
            return false;
        }

        return in_array($this->cod_collection_status, [
            self::COD_NONE,
            self::COD_COLLECTED,
        ], true);
    }
}
