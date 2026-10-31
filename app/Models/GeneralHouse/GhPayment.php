<?php

namespace App\Models\GeneralHouse;

use App\Casts\GhUtcDateTime;
use App\Models\User;
use App\Support\GeneralHouse\GhPaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GhPayment extends Model
{
    protected $table = 'gh_payments';

    protected $fillable = [
        'invoice_id', 'reference', 'method', 'status', 'amount', 'currency',
        'external_ref', 'idempotency_key', 'notes',
        'collector_user_id', 'recorded_by', 'confirmed_by', 'cancelled_by',
        'occurred_at', 'recorded_at', 'confirmed_at', 'cancelled_at',
    ];

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
}
