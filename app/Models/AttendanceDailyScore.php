<?php

namespace App\Models;

use Database\Factories\AttendanceDailyScoreFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-TL daily scores on an attendance row (schema §7c / T-8.5).
 */
class AttendanceDailyScore extends Model
{
    /** @use HasFactory<AttendanceDailyScoreFactory> */
    use HasFactory;

    protected $fillable = [
        'attendance_id',
        'team_leader_id',
        'scored_by',
        'performance_score',
        'behavior_score',
        'productivity_score',
        'interest_return_score',
    ];

    protected function casts(): array
    {
        return [
            'performance_score' => 'decimal:2',
            'behavior_score' => 'decimal:2',
            'productivity_score' => 'decimal:2',
            'interest_return_score' => 'decimal:2',
        ];
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    public function teamLeader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'team_leader_id');
    }

    public function scoredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scored_by');
    }
}
