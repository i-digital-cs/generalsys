<?php

namespace App\Models\GeneralHouse;

use App\Casts\GhUtcDateTime;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Part of a driver cash settlement applied by a manager to one specific collection.
 * Written only by GhDriverCashSettlementService; never updated or deleted.
 */
class GhDriverCashSettlementAllocation extends Model
{
    protected $table = 'gh_driver_cash_settlement_allocations';

    /** @var list<string> */
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'allocated_at' => GhUtcDateTime::class,
            'settlement_id' => 'integer',
            'collection_id' => 'integer',
            'driver_id' => 'integer',
            'allocated_by' => 'integer',
        ];
    }

    /** Allocations whose settlement has not been reversed — the only ones that settle cash. */
    public function scopeEffective(Builder $query): Builder
    {
        return $query->whereNotExists(function ($sub) {
            $sub->selectRaw('1')
                ->from('gh_driver_cash_settlement_reversals')
                ->whereColumn('gh_driver_cash_settlement_reversals.settlement_id', 'gh_driver_cash_settlement_allocations.settlement_id');
        });
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(GhDriverCashSettlement::class, 'settlement_id');
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(GhDriverCashCollection::class, 'collection_id');
    }

    public function allocator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'allocated_by');
    }
}
