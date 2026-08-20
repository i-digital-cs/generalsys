<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Optional image attached at clock-in or clock-out (schema §7b).
 */
class AttendancePhoto extends Model
{
    public const KIND_CLOCK_IN = 'CLOCK_IN';

    public const KIND_CLOCK_OUT = 'CLOCK_OUT';

    protected $fillable = [
        'attendance_id',
        'kind',
        'file_path',
        'original_name',
        'description',
        'mime_type',
        'size_bytes',
        'sort_order',
        'uploaded_at',
    ];

    protected function casts(): array
    {
        return [
            'uploaded_at' => 'datetime',
            'size_bytes' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }
}
