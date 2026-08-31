<?php

namespace App\Models\Gross;

use Illuminate\Database\Eloquent\Model;

class GrossPurchaseReturnSequence extends Model
{
    protected $table = 'gross_purchase_return_sequences';

    public $timestamps = false;

    protected $fillable = [
        'year_month',
        'last_number',
    ];

    protected function casts(): array
    {
        return [
            'last_number' => 'integer',
        ];
    }
}
