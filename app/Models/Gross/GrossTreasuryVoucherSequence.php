<?php

namespace App\Models\Gross;

use Illuminate\Database\Eloquent\Model;

class GrossTreasuryVoucherSequence extends Model
{
    protected $table = 'gross_treasury_voucher_sequences';

    public $timestamps = false;

    protected $fillable = [
        'year_month',
        'kind',
        'last_number',
    ];

    protected function casts(): array
    {
        return [
            'last_number' => 'integer',
        ];
    }
}
