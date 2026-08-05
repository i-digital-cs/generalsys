<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A single rest-day date for an employee (schema doc §20) — marked per date.
 */
class RestDay extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'date',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** The pending vacation request reserving this rest day as a swap, if any. */
    public function pendingReservation(): HasOne
    {
        return $this->hasOne(Vacation::class, 'replace_rest_day_id')
            ->where('status', Vacation::STATUS_PENDING);
    }
}
