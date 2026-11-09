<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable audit row for payroll status transitions.
 */
class PayrollStatusAudit extends Model
{
    public const ACTION_REVERT_APPROVED_TO_DRAFT = 'revert_approved_to_draft';

    protected $fillable = [
        'payroll_record_id',
        'user_id',
        'actor_id',
        'from_status',
        'to_status',
        'action',
        'reason',
        'meta_json',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'meta_json' => 'array',
            'recorded_at' => 'datetime',
        ];
    }

    public function payrollRecord(): BelongsTo
    {
        return $this->belongsTo(PayrollRecord::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
