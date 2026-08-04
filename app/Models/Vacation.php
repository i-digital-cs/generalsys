<?php

namespace App\Models;

use Database\Factories\VacationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Vacation request (schema doc §9). */
class Vacation extends Model
{
    /** @use HasFactory<VacationFactory> */
    use HasFactory;

    public const TYPE_ANNUAL = 'ANNUAL';

    public const TYPE_SICK = 'SICK';

    public const TYPE_EMERGENCY = 'EMERGENCY';

    public const STATUS_PENDING = 'PENDING';

    public const STATUS_APPROVED = 'APPROVED';

    public const STATUS_REJECTED = 'REJECTED';

    public const STATUS_FORCED = 'FORCED';

    protected $fillable = [
        'user_id',
        'type',
        'start_date',
        'end_date',
        'days_requested',
        'replace_rest_date',
        'replace_rest_day_id',
        'status',
        'team_leader_id',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'replace_rest_date' => 'date',
            'days_requested' => 'decimal:1',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function teamLeader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'team_leader_id');
    }

    public function replaceRestDay(): BelongsTo
    {
        return $this->belongsTo(RestDay::class, 'replace_rest_day_id');
    }
}
