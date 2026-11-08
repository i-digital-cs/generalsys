<?php

namespace App\Models\GeneralHouse;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GhAppVersionPolicyAudit extends Model
{
    protected $table = 'gh_app_version_policy_audits';

    protected $fillable = [
        'policy_id',
        'actor_id',
        'action',
        'before_json',
        'after_json',
        'reason',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'before_json' => 'array',
            'after_json' => 'array',
            'recorded_at' => 'datetime',
        ];
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(GhAppVersionPolicy::class, 'policy_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
