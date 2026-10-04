<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Unpaid gap between two segments on the same shift (T-9.1).
 */
class AttendanceGap extends Model
{
    public const TL_PENDING = 'PENDING';

    public const TL_APPROVE = 'APPROVE';

    public const TL_REJECT = 'REJECT';

    public const ADMIN_PENDING = 'PENDING';

    public const ADMIN_APPROVED = 'APPROVED';

    public const ADMIN_REJECTED = 'REJECTED';

    protected $fillable = [
        'attendance_id',
        'gap_start',
        'gap_end',
        'minutes',
        'tl_recommendation',
        'tl_decided_by',
        'tl_decided_at',
        'tl_note',
        'admin_decision',
        'admin_decided_by',
        'admin_decided_at',
        'admin_note',
    ];

    protected function casts(): array
    {
        return [
            'gap_start' => 'datetime',
            'gap_end' => 'datetime',
            'tl_decided_at' => 'datetime',
            'admin_decided_at' => 'datetime',
        ];
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    public function tlDecider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tl_decided_by');
    }

    public function adminDecider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_decided_by');
    }

    public function isPendingForAdmin(): bool
    {
        return $this->admin_decision === self::ADMIN_PENDING
            && $this->tl_recommendation !== self::TL_PENDING;
    }

    /** Admin rejected — TL may recommend again (two-way). */
    public function isAwaitingTlRereview(): bool
    {
        return $this->admin_decision === self::ADMIN_REJECTED;
    }

    public function isPendingForTl(): bool
    {
        return $this->tl_recommendation === self::TL_PENDING
            || $this->isAwaitingTlRereview();
    }
}
