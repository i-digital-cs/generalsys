<?php

namespace App\Models\GeneralHouse;

use App\Casts\GhUtcDateTime;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GhOrderEvent extends Model
{
    protected $table = 'gh_order_events';

    protected $fillable = [
        'order_id',
        'event_type',
        'from_status',
        'to_status',
        'actor_id',
        'occurred_at',
        'recorded_at',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => GhUtcDateTime::class,
            'recorded_at' => GhUtcDateTime::class,
            'payload' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(GhOrder::class, 'order_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
