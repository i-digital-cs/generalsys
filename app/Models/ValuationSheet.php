<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ValuationSheet extends Model
{
    public const STATUS_DRAFT = 'DRAFT';

    public const STATUS_SUBMITTED = 'SUBMITTED';

    protected $fillable = [
        'user_id',
        'module',
        'date',
        'status',
        'input',
        'result',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'input' => 'array',
            'result' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
