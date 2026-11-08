<?php

namespace App\Models\GeneralHouse;

use App\Casts\GhUtcDateTime;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GhRefundEvent extends Model
{
    protected $table = 'gh_refund_events';

    /** @var list<string> */
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'occurred_at' => GhUtcDateTime::class,
            'payload' => 'array',
        ];
    }

    public function refund(): BelongsTo
    {
        return $this->belongsTo(GhRefund::class, 'refund_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
