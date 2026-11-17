<?php

namespace App\Models\GeneralHouse;

use App\Casts\GhUtcDateTime;
use App\Models\User;
use App\Support\GeneralHouse\GhDebitAdjustmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GhDebitAdjustment extends Model
{
    protected $table = 'gh_debit_adjustments';

    /** @var list<string> */
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'lines_net' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'total_debit' => 'decimal:2',
            'standard_vat_rate' => 'decimal:2',
            'issued_at' => GhUtcDateTime::class,
            'cancelled_at' => GhUtcDateTime::class,
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(GhInvoice::class, 'invoice_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(GhOrder::class, 'order_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(GhDebitAdjustmentLine::class, 'debit_adjustment_id')->orderBy('sort_order')->orderBy('id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(GhDebitAdjustmentEvent::class, 'debit_adjustment_id')->orderBy('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function isDraft(): bool
    {
        return $this->status === GhDebitAdjustmentStatus::DRAFT;
    }

    public function isIssued(): bool
    {
        return $this->status === GhDebitAdjustmentStatus::ISSUED;
    }
}
