<?php

namespace App\Models\GeneralHouse;

use App\Casts\GhUtcDateTime;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GhMinOrderOverride extends Model
{
    protected $table = 'gh_min_order_overrides';

    protected $fillable = [
        'invoice_id', 'order_id', 'override_type',
        'original_minimum', 'effective_minimum', 'adjustment_amount',
        'reason', 'actor_id', 'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'original_minimum' => 'decimal:2',
            'effective_minimum' => 'decimal:2',
            'adjustment_amount' => 'decimal:2',
            'recorded_at' => GhUtcDateTime::class,
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(GhInvoice::class, 'invoice_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
