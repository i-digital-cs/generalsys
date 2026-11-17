<?php

namespace App\Models\GeneralHouse;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GhDriverCashBalance extends Model
{
    protected $table = 'gh_driver_cash_balances';

    protected $primaryKey = 'driver_id';

    public $incrementing = false;

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'driver_id' => 'integer',
            'collected_total' => 'decimal:2',
            'settled_total' => 'decimal:2',
            'outstanding' => 'decimal:2',
            'updated_at' => 'datetime',
        ];
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }
}
