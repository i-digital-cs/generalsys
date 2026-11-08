<?php

namespace App\Models\GeneralHouse;

use App\Casts\GhUtcDateTime;
use App\Models\User;
use App\Support\GeneralHouse\GhFinancialCorrectionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GhFinancialCorrection extends Model
{
    protected $table = 'gh_financial_corrections';

    /** @var list<string> */
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payload' => 'array',
            'requested_at' => GhUtcDateTime::class,
            'approved_at' => GhUtcDateTime::class,
            'rejected_at' => GhUtcDateTime::class,
            'executed_at' => GhUtcDateTime::class,
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(GhInvoice::class, 'invoice_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(GhPayment::class, 'payment_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(GhFinancialCorrectionEvent::class, 'financial_correction_id')->orderBy('id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function isExecuted(): bool
    {
        return $this->status === GhFinancialCorrectionStatus::EXECUTED;
    }
}
