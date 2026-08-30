<?php

namespace App\Models\Gross;

use Illuminate\Database\Eloquent\Model;

class GrossSupplier extends Model
{
    protected $table = 'gross_suppliers';

    protected $fillable = [
        'name',
        'phone',
        'address',
        'notes',
    ];
}
