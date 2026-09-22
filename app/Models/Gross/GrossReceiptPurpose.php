<?php

namespace App\Models\Gross;

use Database\Factories\GrossReceiptPurposeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GrossReceiptPurpose extends Model
{
    /** @use HasFactory<GrossReceiptPurposeFactory> */
    use HasFactory;

    protected $table = 'gross_receipt_purposes';

    protected $fillable = [
        'name_ar',
        'name_en',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function newFactory(): GrossReceiptPurposeFactory
    {
        return GrossReceiptPurposeFactory::new();
    }

    public function vouchers(): HasMany
    {
        return $this->hasMany(GrossTreasuryVoucher::class, 'receipt_purpose_id');
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
