<?php

namespace App\Models;

use Database\Factories\AttendanceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Daily attendance row (schema doc §7) — thin: casts + relations only.
 */
class Attendance extends Model
{
    /** @use HasFactory<AttendanceFactory> */
    use HasFactory;

    protected $table = 'attendance';

    public const SHIFT_FULL_TIME = 'FULL_TIME';

    public const SHIFT_ONLINE_HALF = 'ONLINE_HALF';

    public const SHIFT_ONLINE_FULL = 'ONLINE_FULL';

    public const SHIFT_EXTRA = 'EXTRA_SHIFT';

    public const SHIFT_OVERTIME = 'OVERTIME';

    public const STATUS_PRESENT = 'PRESENT';

    public const STATUS_LATE = 'LATE';

    public const STATUS_EARLY_LEAVE = 'EARLY_LEAVE';

    public const STATUS_REST_DAY = 'REST_DAY';

    public const STATUS_OFFICIAL_HOLIDAY = 'OFFICIAL_HOLIDAY';

    public const STATUS_EXCUSED_ABSENCE = 'EXCUSED_ABSENCE';

    public const STATUS_UNEXCUSED_ABSENCE = 'UNEXCUSED_ABSENCE';

    public const STATUS_VACATION = 'VACATION';

    protected $fillable = [
        'user_id',
        'date',
        'shift_type',
        'is_friday_work',
        'is_day_off_work',
        'is_official_holiday_work',
        'assigned_by_tl_id',
        'clock_in',
        'clock_out',
        'planned_hours',
        'actual_hours',
        'status',
        'late_minutes',
        'early_departure_minutes',
        'is_auto_clock_out',
        'daily_report',
        'team_leader_response',
        'performance_score',
        'behavior_score',
        'productivity_score',
        'interest_return_score',
        'attendance_score',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_friday_work' => 'boolean',
            'is_day_off_work' => 'boolean',
            'is_official_holiday_work' => 'boolean',
            'is_auto_clock_out' => 'boolean',
            'clock_in' => 'datetime',
            'clock_out' => 'datetime',
            'planned_hours' => 'decimal:2',
            'actual_hours' => 'decimal:2',
            'performance_score' => 'decimal:2',
            'behavior_score' => 'decimal:2',
            'productivity_score' => 'decimal:2',
            'interest_return_score' => 'decimal:2',
            'attendance_score' => 'decimal:2',
        ];
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
