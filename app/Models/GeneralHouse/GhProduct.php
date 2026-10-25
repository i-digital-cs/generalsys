<?php

namespace App\Models\GeneralHouse;

use App\Support\GeneralHouse\GhCatalogStatus;
use Database\Factories\GeneralHouse\GhProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class GhProduct extends Model
{
    /** @use HasFactory<GhProductFactory> */
    use HasFactory;

    protected $table = 'gh_products';

    protected $fillable = [
        'category_id',
        'code',
        'name_ar',
        'name_en',
        'image_path',
        'status',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'category_id' => 'integer',
        ];
    }

    protected static function newFactory(): GhProductFactory
    {
        return GhProductFactory::new();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(GhServiceCategory::class, 'category_id');
    }

    public function offerings(): HasMany
    {
        return $this->hasMany(GhProductOffering::class, 'product_id');
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

    public function imageUrl(): ?string
    {
        if ($this->image_path === null || $this->image_path === '') {
            return null;
        }

        if (! Storage::disk('local')->exists($this->image_path)) {
            return null;
        }

        return route('gh.products.image', $this);
    }
}
