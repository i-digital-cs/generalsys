<?php

namespace App\Models\GeneralHouse;

use App\Support\GeneralHouse\GhCatalogStatus;
use Database\Factories\GeneralHouse\GhServiceCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GhServiceCategory extends Model
{
    /** @use HasFactory<GhServiceCategoryFactory> */
    use HasFactory;

    protected $table = 'gh_service_categories';

    protected $fillable = [
        'name_ar',
        'name_en',
        'description_ar',
        'description_en',
        'status',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    protected static function newFactory(): GhServiceCategoryFactory
    {
        return GhServiceCategoryFactory::new();
    }

    public function products(): HasMany
    {
        return $this->hasMany(GhProduct::class, 'category_id');
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
