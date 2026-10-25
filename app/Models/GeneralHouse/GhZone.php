<?php

namespace App\Models\GeneralHouse;

use App\Support\GeneralHouse\GhCatalogStatus;
use Database\Factories\GeneralHouse\GhZoneFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GhZone extends Model
{
    /** @use HasFactory<GhZoneFactory> */
    use HasFactory;

    protected $table = 'gh_zones';

    protected $fillable = [
        'name_ar',
        'name_en',
        'min_order_amount',
        'min_order_policy',
        'status',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'min_order_amount' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    protected static function newFactory(): GhZoneFactory
    {
        return GhZoneFactory::new();
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(GhCustomerAddress::class, 'zone_id');
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
