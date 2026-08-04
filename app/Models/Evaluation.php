<?php

namespace App\Models;

use Database\Factories\EvaluationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Monthly evaluation averages + narrative (schema doc §12).
 */
class Evaluation extends Model
{
    /** @use HasFactory<EvaluationFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'team_leader_id',
        'month',
        'year',
        'performance_score',
        'behavior_score',
        'productivity_score',
        'interest_return_score',
        'attendance_score',
        'total_score',
        'evaluation_text',
    ];

    protected function casts(): array
    {
        return [
            'performance_score' => 'decimal:2',
            'behavior_score' => 'decimal:2',
            'productivity_score' => 'decimal:2',
            'interest_return_score' => 'decimal:2',
            'attendance_score' => 'decimal:2',
            'total_score' => 'decimal:2',
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
}
