<?php

namespace App\Models\GeneralHouse;

use App\Casts\GhUtcDateTime;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GhFinancialCorrectionEvent extends Model
{
    protected $table = 'gh_financial_correction_events';

    /** @var list<string> */
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'occurred_at' => GhUtcDateTime::class,
            'payload' => 'array',
        ];
    }

    public function correction(): BelongsTo
    {
        return $this->belongsTo(GhFinancialCorrection::class, 'financial_correction_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
