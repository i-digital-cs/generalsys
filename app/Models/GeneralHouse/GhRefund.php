<?php

namespace App\Models\GeneralHouse;

use App\Casts\GhUtcDateTime;
use App\Models\User;
use App\Support\GeneralHouse\GhRefundStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GhRefund extends Model
{
    protected $table = 'gh_refunds';

    /** @var list<string> */
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'requested_at' => GhUtcDateTime::class,
            'approved_at' => GhUtcDateTime::class,
            'rejected_at' => GhUtcDateTime::class,
            'paid_out_at' => GhUtcDateTime::class,
            'cancelled_at' => GhUtcDateTime::class,
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(GhInvoice::class, 'invoice_id');
    }

    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(GhCreditNote::class, 'credit_note_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(GhRefundEvent::class, 'refund_id')->orderBy('id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function isPaidOut(): bool
    {
        return $this->status === GhRefundStatus::PAID_OUT;
    }
}
