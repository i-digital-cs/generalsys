<?php

namespace App\Models\GeneralHouse;

use App\Casts\GhUtcDateTime;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GhCreditNoteEvent extends Model
{
    protected $table = 'gh_credit_note_events';

    /** @var list<string> */
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'occurred_at' => GhUtcDateTime::class,
            'payload' => 'array',
        ];
    }

    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(GhCreditNote::class, 'credit_note_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
