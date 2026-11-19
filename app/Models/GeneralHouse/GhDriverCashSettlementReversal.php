<?php

namespace App\Models\GeneralHouse;

use App\Casts\GhUtcDateTime;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Compensating entry that cancels one confirmed driver cash settlement (at most one per settlement).
 */
class GhDriverCashSettlementReversal extends Model
{
    protected $table = 'gh_driver_cash_settlement_reversals';

    /** @var list<string> */
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'reversed_at' => GhUtcDateTime::class,
            'settlement_id' => 'integer',
            'driver_id' => 'integer',
            'reversed_by' => 'integer',
        ];
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(GhDriverCashSettlement::class, 'settlement_id');
    }

    public function reverser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }
}
