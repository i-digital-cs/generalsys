<?php

namespace App\Models\GeneralHouse;

use App\Casts\GhUtcDateTime;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GhDriverCashEvent extends Model
{
    protected $table = 'gh_driver_cash_events';

    /** @var list<string> */
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'occurred_at' => GhUtcDateTime::class,
            'payload' => 'array',
            'driver_id' => 'integer',
            'collection_id' => 'integer',
            'settlement_id' => 'integer',
            'actor_id' => 'integer',
        ];
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(GhDriverCashCollection::class, 'collection_id');
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(GhDriverCashSettlement::class, 'settlement_id');
    }
}
