<?php

namespace App\Models\GeneralHouse;

use App\Models\User;
use App\Support\GeneralHouse\GhOrderStatus;
use Database\Factories\GeneralHouse\GhOrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GhOrder extends Model
{
    /** @use HasFactory<GhOrderFactory> */
    use HasFactory;

    protected $table = 'gh_orders';

    protected $fillable = [
        'reference',
        'customer_id',
        'address_id',
        'zone_id',
        'contract_id',
        'status',
        'pickup_starts_at',
        'pickup_ends_at',
        'timezone',
        'notes',
        'scheduling_notes',
        'pickup_driver_id',
        'delivery_driver_id',
        'estimated_subtotal',
        'currency',
        'picked_up_at',
        'invoice_issued_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'pickup_starts_at' => 'datetime',
            'pickup_ends_at' => 'datetime',
            'picked_up_at' => 'datetime',
            'invoice_issued_at' => 'datetime',
            'estimated_subtotal' => 'decimal:2',
            'customer_id' => 'integer',
            'address_id' => 'integer',
            'zone_id' => 'integer',
            'contract_id' => 'integer',
            'pickup_driver_id' => 'integer',
            'delivery_driver_id' => 'integer',
        ];
    }

    protected static function newFactory(): GhOrderFactory
    {
        return GhOrderFactory::new();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(GhCustomer::class, 'customer_id');
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(GhCustomerAddress::class, 'address_id');
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(GhZone::class, 'zone_id');
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(GhB2bContract::class, 'contract_id');
    }

    public function pickupDriver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pickup_driver_id');
    }

    public function deliveryDriver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivery_driver_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(GhOrderLine::class, 'order_id')->orderBy('sort_order')->orderBy('id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(GhOrderAssignment::class, 'order_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(GhOrderEvent::class, 'order_id')->orderByDesc('occurred_at')->orderByDesc('id');
    }

    public function isEditableOperationally(): bool
    {
        if ($this->invoice_issued_at !== null || $this->picked_up_at !== null) {
            return false;
        }

        return GhOrderStatus::isPrePickup($this->status);
    }
}
