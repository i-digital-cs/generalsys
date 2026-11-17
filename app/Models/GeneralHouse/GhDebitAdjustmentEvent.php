<?php

namespace App\Models\GeneralHouse;

use App\Casts\GhUtcDateTime;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GhDebitAdjustmentEvent extends Model
{
    protected $table = 'gh_debit_adjustment_events';

    /** @var list<string> */
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'occurred_at' => GhUtcDateTime::class,
            'payload' => 'array',
        ];
    }

    public function debitAdjustment(): BelongsTo
    {
        return $this->belongsTo(GhDebitAdjustment::class, 'debit_adjustment_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
