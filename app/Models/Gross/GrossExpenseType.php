<?php

namespace App\Models\Gross;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GrossExpenseType extends Model
{
    protected $table = 'gross_expense_types';

    protected $fillable = [
        'name_ar',
        'name_en',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function vouchers(): HasMany
    {
        return $this->hasMany(GrossTreasuryVoucher::class, 'expense_type_id');
    }

    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    public function localizedName(?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();

        return $locale === 'ar' ? $this->name_ar : $this->name_en;
    }
}
