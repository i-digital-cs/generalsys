<?php

namespace App\Models\GeneralHouse;

use App\Casts\GhUtcDateTime;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GhDriverCashCollection extends Model
{
    protected $table = 'gh_driver_cash_collections';

    /** @var list<string> */
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'collected_at' => GhUtcDateTime::class,
            'driver_id' => 'integer',
            'order_id' => 'integer',
            'payment_id' => 'integer',
            'recorded_by' => 'integer',
        ];
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(GhOrder::class, 'order_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(GhPayment::class, 'payment_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(GhDriverCashSettlementAllocation::class, 'collection_id')->orderBy('id');
    }
}
