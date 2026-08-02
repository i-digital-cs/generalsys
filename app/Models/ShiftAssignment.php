<?php

namespace App\Models;

use Database\Factories\ShiftAssignmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** TL pre-assignment of EXTRA_SHIFT / OVERTIME (schema doc §17). */
class ShiftAssignment extends Model
{
    /** @use HasFactory<ShiftAssignmentFactory> */
    use HasFactory;

    public const TYPE_EXTRA = 'EXTRA_SHIFT';

    public const TYPE_OVERTIME = 'OVERTIME';

    public const STATUS_SCHEDULED = 'SCHEDULED';

    public const STATUS_COMPLETED = 'COMPLETED';

    public const STATUS_CANCELLED = 'CANCELLED';

    protected $fillable = [
        'user_id',
        'assigned_by_tl_id',
        'date',
        'shift_type',
        'expected_start_time',
        'expected_end_time',
        'expected_hours',
        'multiplier',
        'notes',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'expected_hours' => 'decimal:2',
            'multiplier' => 'decimal:2',
        ];
    }

    public function getExpectedStartTimeAttribute(?string $value): ?string
    {
        return $value === null ? null : substr($value, 0, 5);
    }

    public function getExpectedEndTimeAttribute(?string $value): ?string
    {
        return $value === null ? null : substr($value, 0, 5);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_tl_id');
    }
}
