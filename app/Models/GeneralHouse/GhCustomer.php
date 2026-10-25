<?php

namespace App\Models\GeneralHouse;

use App\Support\GeneralHouse\GhCatalogStatus;
use App\Support\GeneralHouse\GhCustomerType;
use Database\Factories\GeneralHouse\GhCustomerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class GhCustomer extends Model
{
    /** @use HasFactory<GhCustomerFactory> */
    use HasFactory;

    protected $table = 'gh_customers';

    protected $fillable = [
        'type',
        'name_ar',
        'name_en',
        'phone',
        'email',
        'billing_name',
        'billing_address',
        'tax_registration_number',
        'status',
        'notes',
    ];

    protected static function newFactory(): GhCustomerFactory
    {
        return GhCustomerFactory::new();
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(GhCustomerAddress::class, 'customer_id');
    }

    public function defaultAddress(): HasOne
    {
        return $this->hasOne(GhCustomerAddress::class, 'customer_id')->where('is_default', true);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(GhB2bContract::class, 'customer_id');
    }

    public function isB2b(): bool
    {
        return $this->type === GhCustomerType::B2B;
    }

    public function isActive(): bool
    {
        return $this->status === GhCatalogStatus::ACTIVE;
    }

    public function localizedName(?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        if ($locale === 'ar') {
            return $this->name_ar;
        }

        return $this->name_en ?: $this->name_ar;
    }
}
