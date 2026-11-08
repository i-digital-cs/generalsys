<?php

namespace App\Models\GeneralHouse;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GhVatRateAudit extends Model
{
    protected $table = 'gh_vat_rate_audits';

    protected $fillable = [
        'actor_id',
        'old_rate',
        'new_rate',
        'reason',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'old_rate' => 'decimal:2',
            'new_rate' => 'decimal:2',
            'recorded_at' => 'datetime',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
