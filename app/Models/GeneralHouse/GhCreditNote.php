<?php

namespace App\Models\GeneralHouse;

use App\Casts\GhUtcDateTime;
use App\Models\User;
use App\Support\GeneralHouse\GhCreditNoteStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GhCreditNote extends Model
{
    protected $table = 'gh_credit_notes';

    /** @var list<string> */
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'lines_net' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'total_credit' => 'decimal:2',
            'standard_vat_rate' => 'decimal:2',
            'submitted_at' => GhUtcDateTime::class,
            'approved_at' => GhUtcDateTime::class,
            'rejected_at' => GhUtcDateTime::class,
            'issued_at' => GhUtcDateTime::class,
            'cancelled_at' => GhUtcDateTime::class,
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(GhInvoice::class, 'invoice_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(GhCreditNoteLine::class, 'credit_note_id')->orderBy('sort_order')->orderBy('id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(GhCreditNoteEvent::class, 'credit_note_id')->orderBy('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isDraft(): bool
    {
        return $this->status === GhCreditNoteStatus::DRAFT;
    }

    public function isIssued(): bool
    {
        return $this->status === GhCreditNoteStatus::ISSUED;
    }
}
