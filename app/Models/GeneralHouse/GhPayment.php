<?php

namespace App\Models\GeneralHouse;

use App\Casts\GhUtcDateTime;
use App\Models\User;
use App\Support\GeneralHouse\GhPaymentMethod;
use App\Support\GeneralHouse\GhPaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Payment ledger rows are written only by GhPaymentService (forceFill/forceCreate).
 */
class GhPayment extends Model
{
    protected $table = 'gh_payments';

    /** @var list<string> */
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'occurred_at' => GhUtcDateTime::class,
            'recorded_at' => GhUtcDateTime::class,
            'confirmed_at' => GhUtcDateTime::class,
            'cancelled_at' => GhUtcDateTime::class,
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(GhInvoice::class, 'invoice_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(GhPaymentEvent::class, 'payment_id')->orderBy('id');
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collector_user_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function isConfirmed(): bool
    {
        return $this->status === GhPaymentStatus::CONFIRMED;
    }

    public function isPending(): bool
    {
        return $this->status === GhPaymentStatus::PENDING;
    }

    public function isPendingReview(): bool
    {
        return $this->status === GhPaymentStatus::PENDING_REVIEW;
    }

    public function isOpenDeclaration(): bool
    {
        return $this->isPendingReview()
            || ($this->isPending() && $this->method === GhPaymentMethod::ONLINE);
    }
}
