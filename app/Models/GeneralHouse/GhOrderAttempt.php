<?php

namespace App\Models\GeneralHouse;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GhOrderAttempt extends Model
{
    protected $table = 'gh_order_attempts';

    protected $fillable = [
        'order_id',
        'attempt_type',
        'outcome',
        'actor_id',
        'occurred_at',
        'recorded_at',
        'notes',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'recorded_at' => 'datetime',
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
