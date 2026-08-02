<?php

namespace App\Models;

use Database\Factories\PermissionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Leave / late permission request (schema doc §8) — not ACL.
 */
class Permission extends Model
{
    /** @use HasFactory<PermissionFactory> */
    use HasFactory;

    public const TYPE_LEAVE = 'LEAVE_PERMISSION';

    public const TYPE_LATE = 'LATE_PERMISSION';

    public const STATUS_PENDING = 'PENDING';

    public const STATUS_ACCEPTED = 'ACCEPTED';

    public const STATUS_REJECTED = 'REJECTED';

    public const STATUS_FORCED = 'FORCED';

    protected $fillable = [
        'user_id',
        'type',
        'request_date',
        'from_time',
        'to_time',
        'reason',
        'status',
        'team_leader_id',
        'penalty_amount',
        'is_double_penalty',
    ];

    protected function casts(): array
    {
        return [
            'request_date' => 'date',
            'penalty_amount' => 'decimal:2',
            'is_double_penalty' => 'boolean',
        ];
    }

    public function getFromTimeAttribute(?string $value): ?string
    {
        return $value === null ? null : substr($value, 0, 5);
    }

    public function getToTimeAttribute(?string $value): ?string
    {
        return $value === null ? null : substr($value, 0, 5);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function teamLeader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'team_leader_id');
    }
}
