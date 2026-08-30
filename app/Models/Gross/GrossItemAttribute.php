<?php

namespace App\Models\Gross;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GrossItemAttribute extends Model
{
    public const KIND_COLOR = 'COLOR';

    public const KIND_SIZE = 'SIZE';

    public const KIND_MODEL = 'MODEL';

    /** @var list<string> */
    public const KINDS = [self::KIND_COLOR, self::KIND_SIZE, self::KIND_MODEL];

    protected $table = 'gross_item_attributes';

    protected $fillable = [
        'kind',
        'name_ar',
        'name_en',
    ];

    public function localizedName(): string
    {
        return app()->getLocale() === 'ar' ? $this->name_ar : $this->name_en;
    }

    public function itemsAsColor(): HasMany
    {
        return $this->hasMany(GrossItem::class, 'color_id');
    }

    public function itemsAsSize(): HasMany
    {
        return $this->hasMany(GrossItem::class, 'size_id');
    }

    public function itemsAsModel(): HasMany
    {
        return $this->hasMany(GrossItem::class, 'model_id');
    }
}
