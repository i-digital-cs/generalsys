<?php

namespace App\Models;

use Database\Factories\VacationCreditLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only vacation credit audit row (schema doc §11) — no updated_at.
 */
class VacationCreditLog extends Model
{
    /** @use HasFactory<VacationCreditLogFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'vacation_balance_id',
        'added_by',
        'amount_days',
        'reason',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_days' => 'decimal:1',
            'created_at' => 'datetime',
        ];
    }

    public function balance(): BelongsTo
    {
        return $this->belongsTo(VacationBalance::class, 'vacation_balance_id');
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}
