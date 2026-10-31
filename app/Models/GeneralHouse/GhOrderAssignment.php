<?php

namespace App\Models\GeneralHouse;

use App\Casts\GhUtcDateTime;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GhOrderAssignment extends Model
{
    protected $table = 'gh_order_assignments';

    protected $fillable = [
        'order_id',
        'driver_user_id',
        'responsibility',
        'assigned_by',
        'assigned_at',
        'unassigned_at',
        'is_current',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => GhUtcDateTime::class,
            'unassigned_at' => GhUtcDateTime::class,
            'is_current' => 'boolean',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(GhOrder::class, 'order_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_user_id');
    }

    public function assignedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
