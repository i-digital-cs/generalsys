<?php

namespace App\Models\Gross;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GrossPurchaseInvoice extends Model
{
    public const STATUS_DRAFT = 'DRAFT';

    public const STATUS_CONFIRMED = 'CONFIRMED';

    public const STATUS_CANCELLED = 'CANCELLED';

    protected $table = 'gross_purchase_invoices';

    protected $fillable = [
        'invoice_number',
        'supplier_id',
        'supplier_name',
        'supplier_phone',
        'supplier_address',
        'currency',
        'discount_amount',
        'discount_percent',
        'subtotal',
        'total',
        'status',
        'notes',
        'confirmed_at',
        'cancelled_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'discount_amount' => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'total' => 'decimal:2',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(GrossSupplier::class, 'supplier_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(GrossPurchaseInvoiceLine::class, 'purchase_invoice_id');
    }

    public function returns(): HasMany
    {
        return $this->hasMany(GrossPurchaseReturn::class, 'purchase_invoice_id');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }
}
