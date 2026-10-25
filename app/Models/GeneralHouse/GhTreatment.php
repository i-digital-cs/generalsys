<?php

namespace App\Models\GeneralHouse;

use App\Support\GeneralHouse\GhCatalogStatus;
use Database\Factories\GeneralHouse\GhTreatmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GhTreatment extends Model
{
    /** @use HasFactory<GhTreatmentFactory> */
    use HasFactory;

    protected $table = 'gh_treatments';

    protected $fillable = [
        'name_ar',
        'name_en',
        'status',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    protected static function newFactory(): GhTreatmentFactory
    {
        return GhTreatmentFactory::new();
    }

    public function offerings(): HasMany
    {
        return $this->hasMany(GhProductOffering::class, 'treatment_id');
    }

    public function isActive(): bool
    {
        return $this->status === GhCatalogStatus::ACTIVE;
    }

    public function localizedName(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return $locale === 'ar' ? $this->name_ar : $this->name_en;
    }
}
