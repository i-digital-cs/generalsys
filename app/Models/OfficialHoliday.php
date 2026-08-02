<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfficialHoliday extends Model
{
    protected $fillable = [
        'date',
        'name_ar',
        'name_en',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }
}
