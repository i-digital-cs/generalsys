<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ValuationAssignment extends Model
{
    public const MODULE_SERVICE = 'service';

    public const MODULE_PRODUCT = 'product';

    public const MODULES = [
        self::MODULE_SERVICE,
        self::MODULE_PRODUCT,
    ];

    protected $fillable = [
        'user_id',
        'assigned_by',
        'module',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
