<?php

namespace App\Models;

use Database\Factories\VacationBalanceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Per-year vacation credit balance (schema doc §10). */
class VacationBalance extends Model
{
    /** @use HasFactory<VacationBalanceFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'year',
        'accrued_days',
        'used_days',
        'pending_days',
        'remaining_days',
        'last_updated_by',
    ];

    protected function casts(): array
    {
        return [
            'accrued_days' => 'decimal:1',
            'used_days' => 'decimal:1',
            'pending_days' => 'decimal:1',
            'remaining_days' => 'decimal:1',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lastUpdater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_updated_by');
    }

    public function creditLogs(): HasMany
    {
        return $this->hasMany(VacationCreditLog::class);
    }
}
