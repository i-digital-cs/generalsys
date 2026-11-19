<?php

namespace App\Models\GeneralHouse;

use App\Casts\GhUtcDateTime;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class GhDriverCashSettlement extends Model
{
    protected $table = 'gh_driver_cash_settlements';

    /** @var list<string> */
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'settled_at' => GhUtcDateTime::class,
            'driver_id' => 'integer',
            'settled_by' => 'integer',
        ];
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function settler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'settled_by');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(GhDriverCashSettlementAllocation::class, 'settlement_id')->orderBy('id');
    }

    public function reversal(): HasOne
    {
        return $this->hasOne(GhDriverCashSettlementReversal::class, 'settlement_id');
    }
}
