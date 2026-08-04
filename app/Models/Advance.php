<?php

namespace App\Models;

use Database\Factories\AdvanceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Salary advance with monthly deduction schedule (schema doc §16).
 */
class Advance extends Model
{
    /** @use HasFactory<AdvanceFactory> */
    use HasFactory;

    public const STATUS_ACTIVE = 'ACTIVE';

    public const STATUS_FULLY_DEDUCTED = 'FULLY_DEDUCTED';

    protected $fillable = [
        'user_id',
        'amount',
        'date_given',
        'deduction_amount_monthly',
        'remaining_amount',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'date_given' => 'date',
            'deduction_amount_monthly' => 'decimal:2',
            'remaining_amount' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
