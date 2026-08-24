<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One clock-in → clock-out interval within a shift (T-9.1).
 */
class AttendanceSegment extends Model
{
    protected $fillable = [
        'attendance_id',
        'sequence',
        'clock_in',
        'clock_out',
        'clock_in_lat',
        'clock_in_lng',
        'clock_in_accuracy_m',
        'clock_in_distance_m',
        'clock_in_outside_geofence',
        'clock_out_lat',
        'clock_out_lng',
        'clock_out_accuracy_m',
        'clock_out_distance_m',
        'clock_out_outside_geofence',
    ];

    protected function casts(): array
    {
        return [
            'clock_in' => 'datetime',
            'clock_out' => 'datetime',
            'clock_in_lat' => 'decimal:7',
            'clock_in_lng' => 'decimal:7',
            'clock_out_lat' => 'decimal:7',
            'clock_out_lng' => 'decimal:7',
            'clock_in_outside_geofence' => 'boolean',
            'clock_out_outside_geofence' => 'boolean',
        ];
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(AttendancePhoto::class)->orderBy('sort_order')->orderBy('id');
    }

    public function isOpen(): bool
    {
        return $this->clock_out === null;
    }

    public function workedMinutes(): int
    {
        if ($this->clock_out === null) {
            return 0;
        }

        return max(0, (int) $this->clock_in->diffInMinutes($this->clock_out, false));
    }
}
