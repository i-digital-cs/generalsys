<?php

namespace App\Models\Gross;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GrossTreasuryAccount extends Model
{
    public const KIND_CASH = 'CASH';

    public const KIND_BANK = 'BANK';

    protected $table = 'gross_treasury_accounts';

    protected $fillable = [
        'kind',
        'name',
        'currency',
        'opening_balance',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function vouchers(): HasMany
    {
        return $this->hasMany(GrossTreasuryVoucher::class, 'account_id');
    }

    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }
}
