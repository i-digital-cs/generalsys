<?php

namespace App\Models\GeneralHouse;

use App\Support\GeneralHouse\GhContractStatus;
use Carbon\CarbonInterface;
use Database\Factories\GeneralHouse\GhB2bContractFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class GhB2bContract extends Model
{
    /** @use HasFactory<GhB2bContractFactory> */
    use HasFactory;

    protected $table = 'gh_b2b_contracts';

    protected $fillable = [
        'customer_id',
        'reference',
        'effective_from',
        'effective_to',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
            'customer_id' => 'integer',
        ];
    }

    protected static function newFactory(): GhB2bContractFactory
    {
        return GhB2bContractFactory::new();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(GhCustomer::class, 'customer_id');
    }

    public function contractOfferings(): HasMany
    {
        return $this->hasMany(GhB2bContractOffering::class, 'contract_id');
    }

    public function isActiveStatus(): bool
    {
        return $this->status === GhContractStatus::ACTIVE;
    }

    public function coversDate(CarbonInterface|string|null $date = null): bool
    {
        $day = $date === null ? Carbon::today() : Carbon::parse($date)->startOfDay();
        if ($day->lt($this->effective_from)) {
            return false;
        }
        if ($this->effective_to !== null && $day->gt($this->effective_to)) {
            return false;
        }

        return true;
    }

    public function isEffectiveOn(CarbonInterface|string|null $date = null): bool
    {
        return $this->isActiveStatus() && $this->coversDate($date);
    }
}
