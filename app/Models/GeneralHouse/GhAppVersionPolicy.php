<?php

namespace App\Models\GeneralHouse;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GhAppVersionPolicy extends Model
{
    protected $table = 'gh_app_version_policies';

    protected $fillable = [
        'platform',
        'channel',
        'latest_version_name',
        'latest_build',
        'minimum_build',
        'update_mode',
        'update_url',
        'release_notes_ar',
        'release_notes_en',
        'publication_status',
        'package_id',
        'sha256',
        'artifact_path',
        'updated_by',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'latest_build' => 'integer',
            'minimum_build' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function audits(): HasMany
    {
        return $this->hasMany(GhAppVersionPolicyAudit::class, 'policy_id');
    }

    public function isPublished(): bool
    {
        return $this->publication_status === 'PUBLISHED'
            && (int) $this->latest_build > 0;
    }
}
