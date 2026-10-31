<?php

namespace App\Models\GeneralHouse;

use App\Casts\GhUtcDateTime;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GhPaymentEvent extends Model
{
    protected $table = 'gh_payment_events';

    protected $fillable = [
        'payment_id', 'invoice_id', 'event_type', 'from_status', 'to_status',
        'actor_id', 'occurred_at', 'payload',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => GhUtcDateTime::class,
            'payload' => 'array',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(GhPayment::class, 'payment_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
